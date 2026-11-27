<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	AdminPluginVisibility,
	AdminPluginVisibilitySnapshot,
	CloakedPluginFinding,
	CloakedPluginState,
	PhpFileActivityClassifier,
	PluginEntry,
	PluginVisibilityComparator,
	RawPluginInventory
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerCloakedPlugins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type CloakedPluginFindingState from CloakedPluginState
 */
class CloakedPluginsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	private bool $isDetecting = false;

	private ?AdminPluginVisibility $visibility = null;

	/**
	 * @var CloakedPluginFindingState|null
	 */
	private ?array $currentState = null;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isPluginEnabled()
			   && self::con()->caps->canDetectCloakedPlugins();
	}

	protected function run() :void {
		$this->visibility = new AdminPluginVisibility();
		add_filter( 'all_plugins', [ $this->visibility, 'beginPluginsList' ], -\PHP_INT_MAX );
		add_filter( 'all_plugins', [ $this->visibility, 'observeAllPlugins' ], \PHP_INT_MAX );
		add_filter( 'show_advanced_plugins', [ $this->visibility, 'observeAdvancedPlugins' ], \PHP_INT_MAX, 2 );
		add_action( 'activated_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'deleted_plugin', [ $this, 'forgetIgnoresOfRemovedPlugins' ], \PHP_INT_MAX - 1, 0 );
		add_action( 'deleted_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'pre_uninstall_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'update_option_active_plugins', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 3 );
		add_action( 'update_site_option_active_sitewide_plugins', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 4 );
		add_filter( 'plugins_list', [ $this, 'observePluginsList' ], \PHP_INT_MAX );
	}

	public function triggerDetection( mixed ...$args ) :void {
		unset( $args );
		$this->detect();
	}

	/**
	 * A plugin whose file has gone loses its ignore however it was removed: run from the hourly cron, before detect(),
	 * and straight after WordPress deletes a plugin, so one reinstalled at the same place within the hour is reviewed
	 * afresh. Not run during ordinary detection, which can fire part way through an update; cron events run one at a
	 * time, and WordPress deletes plugins outside its updates.
	 */
	public function forgetIgnoresOfRemovedPlugins() :void {
		if ( !$this->canRun() ) {
			return;
		}
		$inventory = $this->inventory();
		$standard = $inventory->scanStandardPlugins();
		// Shield is a standard plugin itself, so finding none means the plugins folder couldn't be read.
		if ( !empty( $standard ) ) {
			( new CloakedPluginState() )->forgetIgnoresOfPluginsNotIn(
				\array_merge( $standard, $inventory->scanMustUsePlugins() )
			);
		}
	}

	protected function inventory() :RawPluginInventory {
		return new RawPluginInventory();
	}

	public function observePluginsList( mixed $plugins ) :mixed {
		$observation = $this->visibility === null ? null : $this->visibility->finishPluginsList( $plugins );
		if ( $observation !== null
			 && !$this->isDetecting
			 && $this->isPluginsListScreen()
			 && $this->isNeutralPluginListContext() ) {
			$this->detect( $observation );
		}

		return $plugins;
	}

	/**
	 * @return list<CloakedPluginFinding>
	 */
	public function currentFindings() :array {
		return $this->currentState()[ 'active' ];
	}

	/**
	 * @return CloakedPluginFindingState
	 */
	public function currentState() :array {
		if ( $this->currentState !== null ) {
			return $this->currentState;
		}

		$this->detect();
		return $this->currentState ?? $this->emptyState();
	}

	/**
	 * @return list<CloakedPluginFinding>
	 */
	public function detect( ?AdminPluginVisibilitySnapshot $visibility = null ) :array {
		if ( $this->isDetecting || !$this->canRun() ) {
			return [];
		}

		$this->isDetecting = true;
		try {
			$classifier = new PhpFileActivityClassifier();
			$entries = \array_values( \array_filter(
				$this->inventory()->scan(),
				static fn( PluginEntry $entry ) :bool => $classifier->classify( $entry->path )->isAlertable()
			) );

			$visibility = $visibility ?? ( new AdminPluginVisibility() )->snapshot();
			$findings = ( new PluginVisibilityComparator() )->compare(
				$entries,
				$visibility
			);
			$state = ( new CloakedPluginState() )->reconcile(
				$findings,
				$entries,
				$visibility
			);
			$this->currentState = $state;

			$newFindings = $state[ 'new_active' ];
			if ( !empty( $newFindings ) ) {
				$this->publishFindings( $newFindings );
			}

			return $state[ 'active' ];
		}
		finally {
			$this->isDetecting = false;
		}
	}

	/**
	 * @param list<CloakedPluginFinding> $findings
	 */
	private function publishFindings( array $findings ) :void {
		foreach ( $findings as $finding ) {
			self::con()->comps->events->fireEvent( 'plugin_hidden_detected', [
				'audit_params' => $finding->toAuditParams(),
			] );
		}

		self::con()->comps->instant_alerts->updateAlertDataFor(
			new AlertHandlerCloakedPlugins(),
			[
				'hidden_plugins' => \array_map(
					static fn( CloakedPluginFinding $finding ) :array => $finding->toAlertData(),
					$findings
				),
			]
		);
	}

	private function isNeutralPluginListContext() :bool {
		$req = Services::Request();
		$status = (string)$req->query( 'plugin_status' );
		$search = (string)$req->query( 's' );
		foreach ( [ 'action', 'action2' ] as $key ) {
			foreach ( [ $_GET[ $key ] ?? '', $_POST[ $key ] ?? '' ] as $action ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read only to tell whether a bulk action is under way, by comparing exactly with '' and '-1'. That decides whether cloaked plugin detection runs on this view; the values themselves aren't stored or shown.
				if ( !\in_array( $action, [ '', '-1' ], true ) ) {
					return false;
				}
			}
		}

		// A multisite site's table can omit network plugins without any cloaking.
		return $search === '' && ( $status === '' || $status === 'all' )
			&& ( !\is_multisite() || \is_network_admin() );
	}

	private function isPluginsListScreen() :bool {
		global $pagenow;
		return $pagenow === 'plugins.php';
	}

	/**
	 * @return CloakedPluginFindingState
	 */
	private function emptyState() :array {
		return [
			'all'        => [],
			'active'     => [],
			'ignored'    => [],
			'new_active' => [],
		];
	}
}

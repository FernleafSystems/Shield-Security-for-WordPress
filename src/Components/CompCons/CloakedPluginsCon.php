<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	AdminPluginVisibility,
	CloakedPluginFinding,
	CloakedPluginState,
	PhpFileActivity,
	PhpFileActivityClassifier,
	PluginEntry,
	PluginPageView,
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

	/**
	 * @var CloakedPluginFindingState|null
	 */
	private ?array $currentState = null;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isPluginEnabled()
			   && self::con()->caps->canDetectCloakedPlugins();
	}

	protected function run() :void {
		add_action( 'activated_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'deleted_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'pre_uninstall_plugin', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 2 );
		add_action( 'update_option_active_plugins', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 3 );
		add_action( 'update_site_option_active_sitewide_plugins', [ $this, 'triggerDetection' ], \PHP_INT_MAX, 4 );
		add_filter( 'plugins_list', [ $this, 'observePluginsList' ], \PHP_INT_MAX );
		( new PluginPageView() )->addHooks();
	}

	public function triggerDetection( mixed ...$args ) :void {
		unset( $args );
		$this->detect();
	}

	public function observePluginsList( mixed $plugins ) :mixed {
		if ( \is_array( $plugins ) && !$this->isDetecting && $this->isNeutralPluginListContext() ) {
			$this->detect( $plugins );
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
	public function detect( ?array $finalPluginsList = null ) :array {
		if ( $this->isDetecting || !$this->canRun() ) {
			return [];
		}

		$this->isDetecting = true;
		try {
			$classifier = new PhpFileActivityClassifier();
			$entries = \array_values( \array_filter(
				( new RawPluginInventory() )->scan(),
				static fn( PluginEntry $entry ) :bool => $classifier->classify( $entry->path )->isAlertable()
			) );

			$findings = ( new PluginVisibilityComparator() )->compare(
				$entries,
				( new AdminPluginVisibility() )->snapshot( $finalPluginsList )
			);
			$state = ( new CloakedPluginState() )->classify( $findings );

			$newFindings = $state[ 'new_active' ];
			if ( !empty( $newFindings ) ) {
				$this->publishFindings( $newFindings );
			}

			if ( $finalPluginsList === null ) {
				$this->currentState = $state;
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

		return $search === '' && ( $status === '' || $status === 'all' );
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

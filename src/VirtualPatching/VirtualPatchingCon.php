<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\{
	RulesetBuilder,
	RulesetFile,
	RulesetStore,
	SiteState
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * Keeps the virtual patching ruleset in step with the site, and turns logged blocks into activity log events.
 *
 * Patches are read from a local file until rule delivery exists. The ruleset is rebuilt hourly and whenever
 * plugins, themes or Shield's settings change. It is removed when Shield is deactivated, when Shield's features
 * are disabled, without the virtual patching capability, on multisite, and when no patch applies.
 */
class VirtualPatchingCon {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const EVENT_BLOCK = 'virtual_patch_block';
	public const OPTION_STATUS = 'icwp_wpsf_virtual_patching_status';
	private const MAX_IMPORT = 500;

	private bool $exportRequired = false;

	private bool $deactivating = false;

	private int $importDropped = 0;

	protected function run() {
		$this->setupCronHooks();

		$requireExport = function () :void {
			$this->exportRequired = true;
		};
		foreach ( [ 'activated_plugin', 'switch_theme', 'upgrader_process_complete' ] as $hook ) {
			add_action( $hook, $requireExport );
		}
		add_action( 'deactivated_plugin', function ( $plugin ) use ( $requireExport ) :void {
			if ( $plugin !== self::con()->base_file ) {
				$requireExport();
			}
		} );
		add_action( self::con()->prefix( 'after_pre_options_store' ), function ( $cfgChanged ) use ( $requireExport ) :void {
			if ( $cfgChanged ) {
				$requireExport();
			}
		} );
		add_action( self::con()->prefix( 'pre_deactivate_plugin' ), function () :void {
			$this->deactivating = true;
			$this->removeRuleset();
		} );
		add_action( 'shutdown', function () :void {
			try {
				if ( !$this->deactivating && ( $this->exportRequired || $this->isFirstExportDue() ) ) {
					$this->export();
				}
			}
			catch ( \Throwable $e ) {
			}
		} );
	}

	public function runHourlyCron() {
		$this->importBlocks();
		$this->export();
	}

	/**
	 * Blocks made before Shield could record an event, by the early pass or as Shield loaded, are logged to a
	 * file and recorded here. The activity log entry's own IP is the cron request's, so each event carries the
	 * blocked request's IP as a parameter. At most MAX_IMPORT entries are recorded per run, so a burst of blocked
	 * requests can't flood the activity log; the number dropped is kept in the status option.
	 */
	public function importBlocks() :void {
		$path = $this->eventsPath();
		$entries = $path === '' ? [] : BlockLog::drain( $path );
		$this->importDropped = \max( 0, \count( $entries ) - self::MAX_IMPORT );
		foreach ( \array_slice( $entries, 0, self::MAX_IMPORT ) as $entry ) {
			$this->recordBlock( $entry );
		}
	}

	public function rulesetPath() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'virtual-patching/ruleset.php' );
	}

	public function eventsPath() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'virtual-patching/events.php' );
	}

	public function patchesPath() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'virtual-patching/patches.json' );
	}

	/**
	 * When the new ruleset can't be written, the previous one is removed rather than kept: it may name a plugin
	 * version that has since been updated, and blocking a fixed site's normal requests is the worse failure.
	 */
	public function export() :void {
		$this->exportRequired = false;
		$path = $this->rulesetPath();
		$patches = $this->isAvailable() ? $this->loadPatches() : [];
		$built = $path === '' || empty( $patches ) ? null : ( new RulesetBuilder() )->build(
			$patches,
			$this->siteState(),
			Services::Request()->ts(),
			[
				'bypass' => $this->bypassSettings( $path ),
				'events' => $this->eventsPath(),
			]
		);

		$written = $built !== null && !empty( $built[ 'ruleset' ][ 'rules' ] ) && ( new RulesetStore() )->write( $path, $built[ 'ruleset' ] );
		if ( $written ) {
			update_option( RequestGuard::OPTION_RULESET_PATH, $path, true );
		}
		else {
			$this->removeRuleset();
		}

		update_option( self::OPTION_STATUS, [
			'ts'              => Services::Request()->ts(),
			'rules'           => \count( $built[ 'ruleset' ][ 'rules' ] ?? [] ),
			'written'         => $written,
			'rejected'        => $built[ 'rejected' ] ?? [],
			'not_applicable'  => $built[ 'not_applicable' ] ?? [],
			'import_dropped'  => $this->importDropped,
		], false );
	}

	/**
	 * Removes the ruleset the request passes would load, wherever the option says it is and at the current path,
	 * so neither a lost option nor a moved cache directory can leave an old ruleset in use.
	 */
	public function removeRuleset() :void {
		$store = new RulesetStore();
		$recorded = get_option( RequestGuard::OPTION_RULESET_PATH );
		foreach ( \array_unique( \array_filter( [ \is_string( $recorded ) ? $recorded : '', $this->rulesetPath() ] ) ) as $path ) {
			$store->delete( $path );
		}
		delete_option( RequestGuard::OPTION_RULESET_PATH );
	}

	/**
	 * Records one block log entry as an activity log event.
	 */
	public function recordBlock( array $entry ) :void {
		$text = fn( $value ) :string => \is_scalar( $value ) ? (string)$value : '';
		self::con()->comps->events->fireEvent( self::EVENT_BLOCK, [
			'audit_params' => [
				'rule'       => $text( $entry[ 'rule' ] ?? '' ),
				'advisories' => \implode( ', ', \array_map( $text, \is_array( $entry[ 'advisories' ] ?? null ) ? $entry[ 'advisories' ] : [] ) ),
				'ip'         => $text( $entry[ 'ip' ] ?? '' ),
				'method'     => $text( $entry[ 'method' ] ?? '' ),
				'path'       => $text( $entry[ 'path' ] ?? '' ),
			],
		] );
	}

	/**
	 * Virtual patching runs only on single sites with Shield's features enabled and the virtual patching capability.
	 */
	private function isAvailable() :bool {
		return !is_multisite()
			   && self::con()->comps->opts_lookup->isPluginEnabled()
			   && self::con()->caps->canVirtualPatching();
	}

	/**
	 * Covers a site that has no ruleset at the current path yet, such as after install or a cache directory move.
	 * Only an administrator's admin page load starts this, at most hourly, so visitors can't trigger rebuilds.
	 */
	private function isFirstExportDue() :bool {
		if ( !is_admin() || wp_doing_ajax() || !current_user_can( 'manage_options' )
			 || get_option( RequestGuard::OPTION_RULESET_PATH ) === $this->rulesetPath() ) {
			return false;
		}
		$status = get_option( self::OPTION_STATUS );
		return Services::Request()->ts() - (int)( \is_array( $status ) ? ( $status[ 'ts' ] ?? 0 ) : 0 ) > \HOUR_IN_SECONDS;
	}

	/**
	 * Until rule delivery exists, patches come from patches.json beside the ruleset: a list of Factory
	 * Candidates, or an object with a 'patches' list.
	 * @return array[]
	 */
	private function loadPatches() :array {
		$path = $this->patchesPath();
		$FS = Services::WpFs();
		if ( $path === '' || !$FS->isAccessibleFile( $path ) ) {
			return [];
		}
		$decoded = \json_decode( (string)$FS->getFileContent( $path ), true );
		$patches = \is_array( $decoded ) && \is_array( $decoded[ 'patches' ] ?? null ) ? $decoded[ 'patches' ] : $decoded;
		return \is_array( $patches ) && \array_is_list( $patches ) ? $patches : [];
	}

	/**
	 * Active plugins and themes are read from the stored options, not through their filters, so a plugin that
	 * unloads plugins for the current request (or troubleshooting mode) can't hide a target from the ruleset.
	 */
	private function siteState() :SiteState {
		$options = wp_load_alloptions();
		$stored = fn( string $key ) => \array_key_exists( $key, $options ) ? maybe_unserialize( $options[ $key ] ) : get_option( $key );

		$activePlugins = $stored( 'active_plugins' );

		$pluginVersions = [];
		foreach ( Services::WpPlugins()->getPlugins() as $file => $headers ) {
			$pluginVersions[ (string)$file ] = (string)( $headers[ 'Version' ] ?? '' );
		}

		$themeVersions = [];
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$themeVersions[ (string)$stylesheet ] = (string)$theme->get( 'Version' );
		}

		return new SiteState(
			\array_values( \array_filter( \is_array( $activePlugins ) ? $activePlugins : [], '\is_string' ) ),
			\array_values( \array_unique( \array_filter( [ $stored( 'stylesheet' ), $stored( 'template' ) ], '\is_string' ) ) ),
			$pluginVersions,
			$themeVersions
		);
	}

	/**
	 * Requests from IPs on Shield's bypass list skip virtual patches. The passes outside Shield can only trust a
	 * fixed $_SERVER key, so when Shield auto-detects the visitor IP, the source detected for an administrator's
	 * request is used, or failing that the previously exported source. Other requests can't be trusted to show
	 * the site's real source: cron requests come from the server, and a visitor could choose their own headers.
	 */
	private function bypassSettings( string $rulesetPath ) :array {
		$source = self::con()->comps->opts_lookup->ipSource();
		if ( $source === 'AUTO_DETECT_IP' ) {
			$detected = Services::WpGeneral()->isCron() || !current_user_can( 'manage_options' ) ? ''
				: (string)self::con()->this_req->request->getIpDetector()->getPublicRequestSource();
			$previous = RulesetFile::read( $rulesetPath )[ 'bypass' ][ 'ip_source' ] ?? null;
			$source = $detected !== '' ? $detected : ( \is_string( $previous ) ? $previous : null );
		}

		return [
			'ip_source' => $source,
			'ips'       => $this->bypassIps(),
		];
	}

	/**
	 * @return string[] IPs and CIDR ranges on Shield's manual and automatic bypass lists
	 */
	protected function bypassIps() :array {
		$loader = new LoadIpRules();
		$loader->wheres = [
			sprintf( "`ir`.`type` IN ('%s','%s')",
				self::con()->db_con->ip_rules::T_MANUAL_BYPASS,
				self::con()->db_con->ip_rules::T_AUTO_BYPASS
			),
		];
		$loader->limit = 1000;
		return \array_values( \array_unique( \array_map(
			fn( $rule ) => (string)$rule->ipAsSubnetRange(),
			$loader->select()
		) ) );
	}
}

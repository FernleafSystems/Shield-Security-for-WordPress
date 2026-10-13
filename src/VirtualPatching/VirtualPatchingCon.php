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
 * plugins, themes or Shield's settings change, and removed when Shield is disabled, deactivated or on multisite.
 */
class VirtualPatchingCon {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const EVENT_BLOCK = 'virtual_patch_block';
	public const OPTION_STATUS = 'icwp_wpsf_virtual_patching_status';
	private const MAX_IMPORT = 500;

	private bool $exportRequired = false;

	protected function run() {
		$this->setupCronHooks();

		$requireExport = function () :void {
			$this->exportRequired = true;
		};
		foreach ( [ 'activated_plugin', 'deactivated_plugin', 'switch_theme', 'upgrader_process_complete' ] as $hook ) {
			add_action( $hook, $requireExport );
		}
		add_action( self::con()->prefix( 'after_pre_options_store' ), $requireExport );
		add_action( self::con()->prefix( 'pre_deactivate_plugin' ), [ $this, 'removeRuleset' ] );
		add_action( 'shutdown', function () :void {
			if ( $this->exportRequired ) {
				$this->export();
			}
		} );

		if ( is_admin() && get_option( RequestGuard::OPTION_RULESET_PATH ) !== $this->rulesetPath() ) {
			$this->exportRequired = true;
		}
	}

	public function runHourlyCron() {
		$this->importBlocks();
		$this->export();
	}

	/**
	 * Blocks made before Shield could record an event, by the early pass or as Shield loaded, are logged to a
	 * file and recorded here. The activity log entry's own IP is the cron request's, so each event carries the
	 * blocked request's IP as a parameter. At most MAX_IMPORT entries are recorded per run, so a burst of blocked
	 * requests can't flood the activity log.
	 */
	public function importBlocks() :void {
		$path = $this->eventsPath();
		if ( $path !== '' ) {
			foreach ( \array_slice( BlockLog::drain( $path ), 0, self::MAX_IMPORT ) as $entry ) {
				$this->recordBlock( $entry );
			}
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

	public function export() :void {
		$this->exportRequired = false;
		$path = $this->rulesetPath();
		$patches = $this->loadPatches();
		if ( $path === '' || empty( $patches ) || is_multisite() || !self::con()->comps->opts_lookup->isPluginEnabled() ) {
			$this->removeRuleset();
			return;
		}

		$built = ( new RulesetBuilder() )->build( $patches, $this->siteState(), Services::Request()->ts(), [
			'bypass' => $this->bypassSettings( $path ),
			'events' => $this->eventsPath(),
		] );

		$written = empty( $built[ 'ruleset' ][ 'rules' ] ) ? false : ( new RulesetStore() )->write( $path, $built[ 'ruleset' ] );
		if ( $written ) {
			update_option( RequestGuard::OPTION_RULESET_PATH, $path, true );
		}
		else {
			$this->removeRuleset();
		}

		update_option( self::OPTION_STATUS, [
			'ts'             => Services::Request()->ts(),
			'rules'          => \count( $built[ 'ruleset' ][ 'rules' ] ),
			'written'        => $written,
			'rejected'       => $built[ 'rejected' ],
			'not_applicable' => $built[ 'not_applicable' ],
		], false );
	}

	public function removeRuleset() :void {
		$path = get_option( RequestGuard::OPTION_RULESET_PATH );
		if ( \is_string( $path ) && $path !== '' ) {
			( new RulesetStore() )->delete( $path );
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

	private function siteState() :SiteState {
		$activePlugins = get_option( 'active_plugins', [] );

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
			\array_values( \array_unique( [ get_stylesheet(), get_template() ] ) ),
			$pluginVersions,
			$themeVersions
		);
	}

	/**
	 * Requests from IPs on Shield's bypass list skip virtual patches. The passes outside Shield can only trust a
	 * fixed $_SERVER key, so when Shield auto-detects the visitor IP, the source it detected for this request is
	 * used, or failing that the previously exported source. Cron requests can't show the real source.
	 */
	private function bypassSettings( string $rulesetPath ) :array {
		$source = self::con()->comps->opts_lookup->ipSource();
		if ( $source === 'AUTO_DETECT_IP' ) {
			$detected = Services::WpGeneral()->isCron() ? ''
				: (string)self::con()->this_req->request->getIpDetector()->getPublicRequestSource();
			$previous = RulesetFile::read( $rulesetPath )[ 'bypass' ][ 'ip_source' ] ?? null;
			$source = $detected !== '' ? $detected : ( \is_string( $previous ) ? $previous : null );
		}

		$loader = new LoadIpRules();
		$loader->wheres = [
			sprintf( "`ir`.`type` IN ('%s','%s')",
				self::con()->db_con->ip_rules::T_MANUAL_BYPASS,
				self::con()->db_con->ip_rules::T_AUTO_BYPASS
			),
		];
		$loader->limit = 1000;

		return [
			'ip_source' => $source,
			'ips'       => \array_values( \array_unique( \array_map(
				fn( $rule ) => (string)$rule->ipAsSubnetRange(),
				$loader->select()
			) ) ),
		];
	}
}

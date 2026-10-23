<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

use FernleafSystems\Orle\VirtualPatchingRules\{
	RuleSchema,
	WordPressFacts
};
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	FeedClient,
	FeedState,
	PublishedRules,
	RuleFeed,
	RuleFileStore,
	RuleFileVerifier,
	TrustedKeys
};
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\{
	LocalCandidates,
	RulesetBuilder,
	RulesetFile,
	RulesetStore,
	SiteState
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * Keeps the virtual patching ruleset in step with the site, and turns logged blocks and observations into activity
 * log events.
 *
 * Rules arrive as signed rule files, one per installed plugin or theme, fetched from the API outside visitors'
 * requests: hourly, and soon after a plugin or theme is updated, activated or switched. Each fetch ends by
 * rebuilding the ruleset, which is also rebuilt whenever plugins, themes or Shield's settings change. It is removed
 * when Shield is deactivated, when Shield's features are disabled, without the virtual patching capability, on
 * multisite, and when no rule applies.
 */
class VirtualPatchingCon {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const EVENT_BLOCK = 'virtual_patch_block';
	public const EVENT_OBSERVED = 'virtual_patch_observed';
	public const EVENT_REJECTED = 'virtual_patch_rejected';
	public const EVENT_FEED = 'virtual_patch_feed';
	public const OPTION_STATUS = 'icwp_wpsf_virtual_patching_status';
	private const MAX_IMPORT = 500;

	/**
	 * Set to true to also load Factory Candidates from patches.json beside the ruleset, for local parity checks.
	 */
	public const LOCAL_PATCHES_CONSTANT = 'SHIELD_VIRTUAL_PATCHING_LOCAL_PATCHES';

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
				$this->scheduleFetch();
			}
		} );
		add_action( self::con()->prefix( 'pre_deactivate_plugin' ), function () :void {
			$this->deactivating = true;
			$this->removeRuleset();
		} );
		// A block at 'init' exits before Shield has set up the rest of its request state, which shutdown must know.
		add_action( RequestGuard::HOOK_BLOCKED, function () :void {
			self::con()->this_req->is_virtual_patch_blocked = true;
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

		$this->setupFetchHooks();
	}

	public function runHourlyCron() {
		$this->importBlocks();
		$this->fetchRules();
		$this->reportRejected();
		$this->reportFeedProblems();
	}

	/**
	 * After a Shield update the ruleset is rebuilt at once, so one of an older format is never left unused, and every
	 * installed plugin and theme is asked about again, since the rule schema may have changed.
	 */
	public function onUpgrade() :void {
		$this->export();
		$this->scheduleFetch();
	}

	/**
	 * Fetches rule files for the installed plugins and themes, or for those named and any never asked about yet,
	 * then rebuilds the ruleset from what is stored. Never runs in a visitor's request: only from cron.
	 *
	 * @param string[]|null $only "kind/slug" keys
	 */
	public function fetchRules( ?array $only = null ) :void {
		if ( $this->isAvailable() && $this->rulesDir() !== '' ) {
			$state = FeedState::load();
			$assets = $this->siteState()->installedAssets();
			if ( $only === null ) {
				$this->forgetRemoved( $assets, $state );
			}
			else {
				$assets = \array_filter( $assets, fn( array $asset, string $key ) :bool => \in_array( $key, $only, true )
					|| $state->asset( $asset[ 'kind' ], $asset[ 'slug' ] ) === null, \ARRAY_FILTER_USE_BOTH );
			}
			$this->ruleFeed()->fetch( $assets, $state, Services::Request()->ts() );
			$state->save();
		}
		$this->export();
	}

	/**
	 * Each patch the last export rejected is recorded as an activity log event, so a patch Shield can't use is
	 * never dropped unseen. A patch is reported again only when its reason changes. This runs on the hourly cron:
	 * an export at shutdown runs after Shield has already written its activity log for the request.
	 */
	public function reportRejected() :void {
		$status = get_option( self::OPTION_STATUS );
		if ( !\is_array( $status ) ) {
			return;
		}
		$rejected = \is_array( $status[ 'rejected' ] ?? null ) ? $status[ 'rejected' ] : [];
		$reported = \is_array( $status[ 'rejected_reported' ] ?? null ) ? $status[ 'rejected_reported' ] : [];
		foreach ( $rejected as $rule => $reason ) {
			if ( ( $reported[ $rule ] ?? null ) !== $reason ) {
				self::con()->comps->events->fireEvent( self::EVENT_REJECTED, [
					'audit_params' => [
						'rule'   => (string)$rule,
						'reason' => (string)$reason,
					],
				] );
			}
		}
		$status[ 'rejected_reported' ] = $rejected;
		update_option( self::OPTION_STATUS, $status, false );
	}

	/**
	 * Each delivery problem is recorded as an activity log event once, and again only when its reason changes: a
	 * refused or unsaved file, a plugin or theme with no successful update for 3 days, an API that hasn't answered
	 * for 3 days, and a rule schema the API no longer serves, which means Shield must be updated to keep receiving
	 * rules.
	 */
	public function reportFeedProblems() :void {
		// Nothing is fetched while virtual patching is unavailable, so nothing can be out of date.
		if ( !$this->isAvailable() || $this->rulesDir() === '' ) {
			return;
		}
		$state = FeedState::load();
		$problems = $state->problems( Services::Request()->ts() );
		foreach ( $problems as $asset => $reason ) {
			if ( ( $state->reported[ $asset ] ?? null ) !== $reason ) {
				self::con()->comps->events->fireEvent( self::EVENT_FEED, [
					'audit_params' => [
						'asset'  => \in_array( $asset, [ 'schema', 'service' ], true ) ? 'all plugins and themes' : (string)$asset,
						'reason' => $reason,
					],
				] );
			}
		}
		$state->reported = $problems;
		$state->save();
	}

	/**
	 * Blocks and observations made before Shield could record an event, by the early pass or as Shield loaded, are
	 * logged to a file and recorded here, blocks first. The activity log entry's own IP is the cron request's, so
	 * each event carries the request's IP as a parameter. At most MAX_IMPORT entries are recorded per run, so a
	 * burst of requests can't flood the activity log; the number dropped is kept in the status option.
	 */
	public function importBlocks() :void {
		$path = $this->eventsPath();
		$entries = $path === '' ? [] : BlockLog::drain( $path );
		$blocks = \array_filter( $entries, fn( array $entry ) :bool => !BlockLog::isLogRule( $entry ) );
		$entries = \array_merge( $blocks, \array_diff_key( $entries, $blocks ) );
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

	public function rulesDir() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'virtual-patching/rules' );
	}

	/**
	 * When the new ruleset can't be written, the previous one is removed rather than kept: it may name a plugin
	 * version that has since been updated, and blocking a fixed site's normal requests is the worse failure.
	 */
	public function export() :void {
		$this->exportRequired = false;
		$path = $this->rulesetPath();
		$loaded = $this->isAvailable() ? $this->loadPatches() : [ 'rules' => [], 'rejected' => [] ];
		$built = $path === '' || empty( $loaded[ 'rules' ] ) ? null : ( new RulesetBuilder() )->build(
			$loaded[ 'rules' ],
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

		$previous = get_option( self::OPTION_STATUS );
		update_option( self::OPTION_STATUS, [
			'ts'                => Services::Request()->ts(),
			'rules'             => \count( $built[ 'ruleset' ][ 'rules' ] ?? [] ),
			'written'           => $written,
			'rejected'          => \array_merge( $loaded[ 'rejected' ], $built[ 'rejected' ] ?? [] ),
			'rejected_reported' => \is_array( $previous ) && \is_array( $previous[ 'rejected_reported' ] ?? null ) ? $previous[ 'rejected_reported' ] : [],
			'not_applicable'    => $built[ 'not_applicable' ] ?? [],
			'import_dropped'    => $this->importDropped,
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
	 * Records one block log entry as an activity log event: a block, or what a log rule would have blocked.
	 */
	public function recordBlock( array $entry ) :void {
		$text = fn( $value ) :string => \is_scalar( $value ) ? (string)$value : '';
		self::con()->comps->events->fireEvent( BlockLog::isLogRule( $entry ) ? self::EVENT_OBSERVED : self::EVENT_BLOCK, [
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
	 * Only an administrator's admin page load starts this, at most hourly, so visitors can't trigger rebuilds. A site
	 * that has never fetched rule files has a fetch scheduled too.
	 */
	private function isFirstExportDue() :bool {
		if ( !is_admin() || wp_doing_ajax() || !current_user_can( 'manage_options' )
			 || get_option( RequestGuard::OPTION_RULESET_PATH ) === $this->rulesetPath() ) {
			return false;
		}
		$status = get_option( self::OPTION_STATUS );
		$due = Services::Request()->ts() - (int)( \is_array( $status ) ? ( $status[ 'ts' ] ?? 0 ) : 0 ) > \HOUR_IN_SECONDS;
		if ( $due && empty( FeedState::load()->assets ) ) {
			$this->scheduleFetch();
		}
		return $due;
	}

	/**
	 * Rules come from the stored rule files, each checked first. Factory Candidates in patches.json are added only
	 * when the development constant is set.
	 * @return array{rules: array[], rejected: array<string, string>}
	 */
	private function loadPatches() :array {
		$loaded = [ 'rules' => [], 'rejected' => [] ];
		if ( $this->rulesDir() !== '' ) {
			$state = FeedState::load();
			$keys = $this->trustedKeys();
			$loaded = ( new PublishedRules( $this->ruleStore(), new RuleFileVerifier( $keys ), $keys, RuleSchema::VERSION ) )
				->load( $state, Services::Request()->ts() );
			$state->save();
		}

		$path = $this->patchesPath();
		$FS = Services::WpFs();
		if ( $this->localPatchesEnabled() && $path !== '' && $FS->isAccessibleFile( $path ) ) {
			$local = ( new LocalCandidates() )->load( (string)$FS->getFileContent( $path ) );
			$loaded = [
				'rules'    => \array_merge( $loaded[ 'rules' ], $local[ 'rules' ] ),
				'rejected' => \array_merge( $loaded[ 'rejected' ], $local[ 'rejected' ] ),
			];
		}
		return $loaded;
	}

	protected function localPatchesEnabled() :bool {
		return \defined( self::LOCAL_PATCHES_CONSTANT ) && \constant( self::LOCAL_PATCHES_CONSTANT ) === true;
	}

	protected function ruleFeed() :RuleFeed {
		return new RuleFeed(
			new FeedClient( self::con()->cfg->version() ),
			$this->ruleStore(),
			new RuleFileVerifier( $this->trustedKeys() ),
			RuleSchema::VERSION
		);
	}

	protected function trustedKeys() :TrustedKeys {
		return new TrustedKeys();
	}

	private function ruleStore() :RuleFileStore {
		return new RuleFileStore( $this->rulesDir() );
	}

	/**
	 * Fetching follows the site's own changes: an update, activation or theme switch asks about the plugins and
	 * themes it names, and any never asked about, in a single scheduled event; activating Shield asks about them
	 * all; deleting a plugin or theme drops its stored file.
	 */
	private function setupFetchHooks() :void {
		$hook = self::con()->prefix( 'virtual_patching_fetch' );
		add_action( $hook, function ( $only = null ) :void {
			$this->fetchRules( \is_array( $only ) ? \array_values( \array_filter( $only, 'is_string' ) ) : null );
		} );
		add_action( 'upgrader_process_complete', function ( $upgrader = null, $extra = [] ) :void {
			$extra = \is_array( $extra ) ? $extra : [];
			$keys = [];
			if ( ( $extra[ 'type' ] ?? '' ) === 'plugin' ) {
				foreach ( \array_merge( (array)( $extra[ 'plugins' ] ?? [] ), (array)( $extra[ 'plugin' ] ?? [] ) ) as $file ) {
					$keys[] = 'plugin/'.self::pluginSlug( (string)$file );
				}
			}
			elseif ( ( $extra[ 'type' ] ?? '' ) === 'theme' ) {
				foreach ( \array_merge( (array)( $extra[ 'themes' ] ?? [] ), (array)( $extra[ 'theme' ] ?? [] ) ) as $theme ) {
					$keys[] = 'theme/'.$theme;
				}
			}
			$this->scheduleFetch( $keys );
		}, 10, 2 );
		add_action( 'activated_plugin', function ( $plugin ) :void {
			if ( \is_string( $plugin ) && $plugin !== self::con()->base_file ) {
				$this->scheduleFetch( [ 'plugin/'.self::pluginSlug( $plugin ) ] );
			}
		} );
		add_action( 'switch_theme', function ( $name = '', $theme = null ) :void {
			if ( \is_object( $theme ) && \method_exists( $theme, 'get_stylesheet' ) ) {
				$this->scheduleFetch( [ 'theme/'.$theme->get_stylesheet() ] );
			}
		}, 10, 2 );
		add_action( 'shield/plugin_activated', fn() => $this->scheduleFetch() );
		add_action( 'deleted_plugin', function ( $plugin, $deleted = false ) :void {
			if ( $deleted && \is_string( $plugin ) ) {
				$this->dropRuleFile( 'plugin', self::pluginSlug( $plugin ) );
			}
		}, 10, 2 );
		add_action( 'deleted_theme', function ( $stylesheet, $deleted = false ) :void {
			if ( $deleted && \is_string( $stylesheet ) ) {
				$this->dropRuleFile( 'theme', $stylesheet );
			}
		}, 10, 2 );
	}

	/**
	 * @param string[]|null $only "kind/slug" keys, or null for every installed plugin and theme
	 */
	private function scheduleFetch( ?array $only = null ) :void {
		if ( !$this->isAvailable() ) {
			return;
		}
		$hook = self::con()->prefix( 'virtual_patching_fetch' );
		$args = [ $only === null ? null : \array_values( \array_unique( $only ) ) ];
		if ( !wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( Services::Request()->ts(), $hook, $args );
		}
	}

	/**
	 * A plugin or theme removed without WordPress's delete hooks, such as over FTP, leaves its record and file
	 * behind; neither is wanted, and its record would otherwise be reported as out of date.
	 *
	 * @param array<string, array{kind: string, slug: string}> $installed
	 */
	private function forgetRemoved( array $installed, FeedState $state ) :void {
		$store = $this->ruleStore();
		foreach ( \array_keys( \array_merge( $state->assets, $store->assets() ) ) as $key ) {
			if ( !isset( $installed[ $key ] ) ) {
				[ $kind, $slug ] = \explode( '/', $key, 2 );
				$store->delete( $kind, $slug );
				$state->forget( $kind, $slug );
			}
		}
	}

	private function dropRuleFile( string $kind, string $slug ) :void {
		$this->ruleStore()->delete( $kind, $slug );
		$state = FeedState::load();
		$state->forget( $kind, $slug );
		$state->save();
		$this->exportRequired = true;
	}

	private static function pluginSlug( string $file ) :string {
		$dir = \dirname( $file );
		return $dir === '.' ? \basename( $file, '.php' ) : $dir;
	}

	private function siteState() :SiteState {
		$pluginVersions = [];
		foreach ( Services::WpPlugins()->getPlugins() as $file => $headers ) {
			$pluginVersions[ (string)$file ] = (string)( $headers[ 'Version' ] ?? '' );
		}

		$themeVersions = [];
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$themeVersions[ (string)$stylesheet ] = (string)$theme->get( 'Version' );
		}

		$activity = WordPressFacts::activity();
		return new SiteState(
			$activity[ 'active_plugins' ],
			$activity[ 'active_themes' ],
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
			// A ruleset of the previous format still names the source detected before an update.
			$previous = RulesetFile::read( $rulesetPath, null )[ 'bypass' ][ 'ip_source' ] ?? null;
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};

/**
 * @phpstan-type FixtureState array{
 *   scenario:string,
 *   options_snapshot:array<string,mixed>,
 *   scan_ids:list<int>,
 *   scan_result_ids:list<int>,
 *   result_item_ids:list<int>,
 *   meta_ids:list<int>,
 *   file_lock_ids:list<int>
 * }
 * @phpstan-type ScenarioContract array{
 *   scenario:string,
 *   bucket_key:string,
 *   group_key:string,
 *   group_section:'active'|'healthy',
 *   detail_shell:string,
 *   panel_target:string,
 *   is_lazy_panel:bool
 * }
 * @phpstan-type ScenarioDefinition array{
 *   scenario:string,
 *   target_group_key:string,
 *   expected_detail_shell:string,
 *   expected_lazy_panel:bool,
 *   require_scan_results_table:bool,
 *   require_populated_scan_results_table:bool
 * }
 */
class ActionsQueueFixtureBuilder {

	private const OPTION_KEYS = [
		'enable_core_file_integrity_scan',
		'enable_wpvuln_scan',
		'enabled_scan_apc',
		'file_scan_areas',
		'file_locker',
		'last_known_cache_basedirs',
		'preferred_temp_dir',
		'snapi_data',
		'filelocker_state',
		'license_data',
		'license_activated_at',
		'license_deactivated_at',
	];

	private const REQUIRED_DB_KEYS = [
		'scans',
		'scan_results',
		'scan_result_items',
		'scan_result_item_meta',
	];

	/** @var ActionsQueueRuntimeProbe|null */
	private $runtimeProbe;

	public function __construct( ?ActionsQueueRuntimeProbe $runtimeProbe = null ) {
		$this->runtimeProbe = $runtimeProbe;
	}

	/**
	 * @return array{contract:ScenarioContract,state:FixtureState}
	 */
	public function seed( string $scenario ) :array {
		RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );

		$state = $this->newFixtureState( $scenario );

		try {
			$this->resetRuntimeState();
			$definition = $this->seedScenario( $scenario, $state );
			RuntimeTestState::resetScanResultCountMemoization();

			return [
				'contract' => $this->verifyScenario( $definition ),
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function inspect( string $scenario ) :array {
		RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );

		$state = $this->newFixtureState( $scenario );

		try {
			$this->resetRuntimeState();
			$definition = $this->seedScenario( $scenario, $state );
			RuntimeTestState::resetScanResultCountMemoization();

			$diagnostics = $this->runtimeProbe()->inspect();
			$groupContext = $this->runtimeProbe()->locateGroupContext( $definition[ 'target_group_key' ] );

			return [
				'scenario'      => $scenario,
				'definition'    => $definition,
				'group_context' => $groupContext,
				'detail'        => $groupContext === null
					? []
					: $this->runtimeProbe()->inspectDetail( $groupContext ),
				'diagnostics'   => $diagnostics,
			];
		}
		finally {
			$this->cleanup( $state );
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$con = RuntimeTestState::controller();

		if ( !empty( $state[ 'file_lock_ids' ] ) ) {
			RuntimeTestState::requireDbHandler( 'file_locker', true );
		}

		foreach ( [
			[ 'file_locker', $state[ 'file_lock_ids' ] ?? [] ],
			[ 'scan_result_item_meta', $state[ 'meta_ids' ] ?? [] ],
			[ 'scan_results', $state[ 'scan_result_ids' ] ?? [] ],
			[ 'scan_result_items', $state[ 'result_item_ids' ] ?? [] ],
			[ 'scans', $state[ 'scan_ids' ] ?? [] ],
		] as [ $dbKey, $ids ] ) {
			foreach ( \is_array( $ids ) ? $ids : [] as $id ) {
				$id = (int)$id;
				if ( $id > 0 ) {
					$con->db_con->{$dbKey}
						->getQueryDeleter()
						->deleteById( $id );
				}
			}
		}

		RuntimeTestState::clearFileLocks();
		RuntimeTestState::resetScanResultCountMemoization();
		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);

		\delete_site_transient( 'update_plugins' );
		\wp_set_current_user( 0 );
		RuntimeTestState::controller()->this_req->is_security_admin = false;
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedScenario( string $scenario, array &$state ) :array {
		switch ( $scenario ) {
			case 'direct_table':
				return $this->seedDirectTable( $state );
			case 'malware_direct_table':
				return $this->seedMalwareDirectTable( $state );
			case 'ignored_plugin_direct_table':
				return $this->seedIgnoredPluginDirectTable( $state );
			case 'ignored_wordpress_direct_table':
				return $this->seedIgnoredWordpressDirectTable( $state );
			case 'ignored_theme_direct_table':
				return $this->seedIgnoredThemeDirectTable( $state );
			case 'ignored_malware_direct_table':
				return $this->seedIgnoredMalwareDirectTable( $state );
			case 'file_locker_lazy':
				return $this->seedFileLockerLazy( $state );
			default:
				throw new \RuntimeException( 'Unknown Actions Queue fixture scenario: '.$scenario );
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'preferred_temp_dir', WP_CONTENT_DIR )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'enable_wpvuln_scan'              => 'Y',
			'enabled_scan_apc'                => 'Y',
			'preferred_temp_dir'              => WP_CONTENT_DIR,
			'file_scan_areas'                 => [ 'wp', 'plugins', 'themes' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$pluginSlug = RuntimeTestState::controller()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );
		$this->trackScanResult( $state, TestDataFactory::insertAfsFileScanResultTracked(
			$scanId,
			$this->pluginMainPathFragment( $pluginSlug ),
			[
				'is_in_plugin'    => 1,
				'is_unrecognised' => 1,
				'ptg_slug'        => $pluginSlug,
			]
		) );
		$vulnerabilityScanId = TestDataFactory::insertCompletedScan( 'wpv' );
		$this->trackId( $state, 'scan_ids', $vulnerabilityScanId );
		$this->trackScanResult( $state, TestDataFactory::insertScanResultItemTracked( $vulnerabilityScanId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] ) );

		return [
			'scenario'                    => 'direct_table',
			'target_group_key'            => 'plugins:'.$pluginSlug,
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => true,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedIgnoredPluginDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'preferred_temp_dir', WP_CONTENT_DIR )
			->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'preferred_temp_dir'              => WP_CONTENT_DIR,
			'file_scan_areas'                 => [ 'wp', 'plugins' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$pluginSlug = RuntimeTestState::controller()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );

		foreach ( [ 1, 2 ] as $_ ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked(
				$scanId,
				$this->pluginMainPathFragment( $pluginSlug ),
				[
					'is_in_plugin' => 1,
					'ptg_slug'     => $pluginSlug,
				]
			);
			$this->trackScanResult( $state, $tracked );
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}

		return [
			'scenario'                    => 'ignored_plugin_direct_table',
			'target_group_key'            => 'plugins:'.$pluginSlug,
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => false,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedIgnoredWordpressDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_file_areas',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'preferred_temp_dir', WP_CONTENT_DIR )
			->optSet( 'file_scan_areas', [ 'wp' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'preferred_temp_dir'              => WP_CONTENT_DIR,
			'file_scan_areas'                 => [ 'wp' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );

		foreach ( [ 'wp-admin/admin.php', 'wp-includes/version.php' ] as $pathFragment ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $pathFragment, [
				'is_in_core' => 1,
			] );
			$this->trackScanResult( $state, $tracked );
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}

		return [
			'scenario'                    => 'ignored_wordpress_direct_table',
			'target_group_key'            => 'wordpress',
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => false,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedIgnoredThemeDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'preferred_temp_dir', WP_CONTENT_DIR )
			->optSet( 'file_scan_areas', [ 'wp', 'themes' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'preferred_temp_dir'              => WP_CONTENT_DIR,
			'file_scan_areas'                 => [ 'wp', 'themes' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$themeSlug = (string)\wp_get_theme()->get_stylesheet();
		if ( $themeSlug === '' ) {
			throw new \RuntimeException( 'Unable to determine the active theme for the Actions Queue fixture.' );
		}

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );

		foreach ( [ 'style.css', 'functions.php' ] as $file ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked(
				$scanId,
				$this->themeFilePathFragment( $themeSlug, $file ),
				[
					'is_in_theme' => 1,
					'ptg_slug'    => $themeSlug,
				]
			);
			$this->trackScanResult( $state, $tracked );
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}

		return [
			'scenario'                    => 'ignored_theme_direct_table',
			'target_group_key'            => 'themes:'.$themeSlug,
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => false,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedIgnoredMalwareDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_malware_local',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'file_scan_areas'                 => [ 'wp', 'malware_php' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );

		foreach ( [ 'wp-config.php', 'index.php' ] as $pathFragment ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $pathFragment, [
				'is_mal' => 1,
			] );
			$this->trackScanResult( $state, $tracked );
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}

		return [
			'scenario'                    => 'ignored_malware_direct_table',
			'target_group_key'            => 'malware',
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => false,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedMalwareDirectTable( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_malware_local',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'enable_core_file_integrity_scan' => 'Y',
			'file_scan_areas'                 => [ 'wp', 'malware_php' ],
		] );
		RuntimeTestState::primeCacheSubDir( 'browser-fixtures-actions-queue' );

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$this->trackId( $state, 'scan_ids', $scanId );
		$this->trackScanResult( $state, TestDataFactory::insertAfsFileScanResultTracked( $scanId, 'wp-config.php', [
			'is_mal' => 1,
		] ) );

		return [
			'scenario'                    => 'malware_direct_table',
			'target_group_key'            => 'malware',
			'expected_detail_shell'       => 'direct_table',
			'expected_lazy_panel'         => false,
			'require_scan_results_table'  => true,
			'require_populated_scan_results_table' => true,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @return ScenarioDefinition
	 */
	private function seedFileLockerLazy( array &$state ) :array {
		RuntimeTestState::applyPremiumCapabilities( [
			'scan_file_locker',
		] );

		RuntimeTestState::controller()->opts
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'file_locker' => [ 'wpconfig' ],
		] );
		RuntimeTestState::primeShieldNetHandshake();
		RuntimeTestState::requireDbHandler( 'file_locker', true );
		RuntimeTestState::controller()->comps->file_locker->canEncrypt( true );

		$file = new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\File(
			'wpconfig',
			'wp-config.php'
		);
		( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CreateFileLocks( $file ) )
			->create();

		RuntimeTestState::clearFileLocks();
		$locks = RuntimeTestState::controller()->comps->file_locker->getLocks();
		$lock = \reset( $locks );
		if ( !$lock ) {
			throw new \RuntimeException( 'Unable to create a file-locker record for the fixture.' );
		}
		$this->trackId( $state, 'file_lock_ids', (int)$lock->id );
		RuntimeTestState::controller()->db_con->file_locker
			->getQueryUpdater()
			->updateCurrentHash( $lock, \sha1( 'browser-fixture-lock-diff' ) );
		$fileLockerState = RuntimeTestState::controller()->comps->file_locker->getState();
		$fileLockerState[ 'last_analysis_started_at' ] = \time();
		RuntimeTestState::controller()->opts
			->optSet( 'filelocker_state', $fileLockerState )
			->store();
		RuntimeTestState::forcePersistOptions( [
			'filelocker_state' => $fileLockerState,
		] );
		RuntimeTestState::clearFileLocks();

		return [
			'scenario'                    => 'file_locker_lazy',
			'target_group_key'            => 'file_locker',
			'expected_detail_shell'       => 'asset_cards',
			'expected_lazy_panel'         => true,
			'require_scan_results_table'  => false,
			'require_populated_scan_results_table' => false,
		];
	}

	private function resetRuntimeState() :void {
		\delete_site_transient( 'update_plugins' );
		RuntimeTestState::clearFileLocks();
		RuntimeTestState::resetScanResultCountMemoization();
	}

	/**
	 * @return FixtureState
	 */
	private function newFixtureState( string $scenario ) :array {
		return [
			'scenario'         => $scenario,
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'scan_ids'         => [],
			'scan_result_ids'  => [],
			'result_item_ids'  => [],
			'meta_ids'         => [],
			'file_lock_ids'    => [],
		];
	}

	/**
	 * @param array<string,mixed> $tracked
	 * @phpstan-param FixtureState $state
	 */
	private function trackScanResult( array &$state, array $tracked ) :void {
		$this->trackId( $state, 'scan_result_ids', (int)( $tracked[ 'scan_result_id' ] ?? 0 ) );
		$this->trackId( $state, 'result_item_ids', (int)( $tracked[ 'result_item_id' ] ?? 0 ) );
		foreach ( \is_array( $tracked[ 'meta_ids' ] ?? null ) ? $tracked[ 'meta_ids' ] : [] as $metaId ) {
			$this->trackId( $state, 'meta_ids', (int)$metaId );
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 * @phpstan-param 'scan_ids'|'scan_result_ids'|'result_item_ids'|'meta_ids'|'file_lock_ids' $key
	 */
	private function trackId( array &$state, string $key, int $id ) :void {
		if ( $id > 0 ) {
			$state[ $key ][] = $id;
		}
	}

	/**
	 * @phpstan-param ScenarioDefinition $definition
	 * @return ScenarioContract
	 */
	private function verifyScenario( array $definition ) :array {
		$diagnostics = $this->runtimeProbe()->inspect();
		if ( !$diagnostics[ 'landing_has_shell' ] ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				'Actions Queue landing render did not expose the drill-down shell.',
				$diagnostics
			) );
		}

		$groupContext = $this->runtimeProbe()->locateGroupContext( $definition[ 'target_group_key' ] );
		if ( $groupContext === null ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				'Unable to locate Actions Queue group context for '.$definition[ 'target_group_key' ],
				$diagnostics
			) );
		}

		$detail = $this->runtimeProbe()->inspectDetail( $groupContext );
		if ( $detail[ 'detail_shell' ] !== $definition[ 'expected_detail_shell' ] ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				\sprintf(
					'Expected detail shell "%s", got "%s".',
					$definition[ 'expected_detail_shell' ],
					$detail[ 'detail_shell' ]
				),
				$diagnostics,
				$groupContext,
				$detail
			) );
		}
		if ( $detail[ 'detail_shell' ] === 'asset_cards' && $detail[ 'panel_target' ] === '' ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				'Asset-card detail did not expose a mode panel target.',
				$diagnostics,
				$groupContext,
				$detail
			) );
		}
		if ( $detail[ 'is_lazy_panel' ] !== $definition[ 'expected_lazy_panel' ] ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				\sprintf(
					'Expected lazy-panel=%s, got %s.',
					$definition[ 'expected_lazy_panel' ] ? 'true' : 'false',
					$detail[ 'is_lazy_panel' ] ? 'true' : 'false'
				),
				$diagnostics,
				$groupContext,
				$detail
			) );
		}
		if ( $definition[ 'require_scan_results_table' ] && !$detail[ 'has_scan_results_table' ] ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				'Detail did not expose the scan results table contract.',
				$diagnostics,
				$groupContext,
				$detail
			) );
		}
		if ( $definition[ 'require_populated_scan_results_table' ]
			 && (int)( $detail[ 'datatable_row_count' ] ?? 0 ) < 1 ) {
			throw new \RuntimeException( $this->buildScenarioFailureMessage(
				$definition[ 'scenario' ],
				'Detail scan results table did not return any rows.',
				$diagnostics,
				$groupContext,
				$detail
			) );
		}

		return [
			'scenario'      => $definition[ 'scenario' ],
			'bucket_key'    => $groupContext[ 'bucket_key' ],
			'group_key'     => $groupContext[ 'group_key' ],
			'group_section' => $groupContext[ 'group_section' ],
			'detail_shell'  => $detail[ 'detail_shell' ],
			'panel_target'  => $detail[ 'panel_target' ],
			'is_lazy_panel' => $detail[ 'is_lazy_panel' ],
		];
	}

	/**
	 * @param array<string,mixed> $diagnostics
	 * @param array<string,mixed> $groupContext
	 * @param array<string,mixed> $detail
	 */
	private function buildScenarioFailureMessage(
		string $scenario,
		string $summary,
		array $diagnostics,
		array $groupContext = [],
		array $detail = []
	) :string {
		$payload = [
			'scenario'    => $scenario,
			'summary'     => $summary,
			'group'       => $groupContext,
			'detail'      => $detail,
			'diagnostics' => $diagnostics,
		];

		$json = \wp_json_encode( $payload );
		return \is_string( $json )
			? $json
			: $summary;
	}

	private function runtimeProbe() :ActionsQueueRuntimeProbe {
		if ( $this->runtimeProbe === null ) {
			$this->runtimeProbe = new ActionsQueueRuntimeProbe();
		}

		return $this->runtimeProbe;
	}

	private function pluginMainPathFragment( string $pluginSlug ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( WP_PLUGIN_DIR.'/'.$pluginSlug );
	}

	private function themeFilePathFragment( string $themeSlug, string $file ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( \get_theme_root().'/'.$themeSlug.'/'.$file );
	}
}

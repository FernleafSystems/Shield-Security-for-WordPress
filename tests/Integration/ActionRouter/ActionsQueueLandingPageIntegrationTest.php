<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MaintenanceItemIgnore;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\ScanResultsLagWarning;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker as FileLockerPane,
	Malware as MalwarePane,
	Plugins as PluginsPane,
	Themes as ThemesPane,
	Vulnerabilities as VulnerabilitiesPane
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueAssetFileStatusDetail;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueGroupsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueLandingAssessmentBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultScopeStateBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ActionRequestNonceFixture,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	private const THEME_FIXTURE_STYLESHEET = 'shield-integration-theme';

	private array $optionsSnapshot = [];
	private array $tempPaths = [];
	private array $tempDirs = [];

	public function set_up() {
		parent::set_up();
		$this->truncateShieldTables();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			MaintenanceIssueStateProvider::OPT_KEY,
			'file_locker',
			'filelocker_state',
			'snapi_data',
		] );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->optSet( MaintenanceIssueStateProvider::OPT_KEY, \array_merge(
				 $this->requireController()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY ) ?? [],
				 [
					 'default_admin_user' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
				 ]
			 ) )
			 ->store();
		$this->resetScanResultCountMemoization();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		foreach ( $this->tempPaths as $path ) {
			if ( \is_string( $path ) && $path !== '' && \file_exists( $path ) ) {
				@\unlink( $path );
			}
		}
		foreach ( \array_reverse( $this->tempDirs ) as $path ) {
			if ( \is_string( $path ) && $path !== '' && \is_dir( $path ) ) {
				@\rmdir( $path );
			}
		}
		if ( \function_exists( 'wp_clean_themes_cache' ) ) {
			\wp_clean_themes_cache( true );
		}
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}

	private function renderActionsQueueLandingPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageActionsQueueLanding::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_SCANS,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_OVERVIEW,
		] );
	}

	private function loadSelectedGroupPayload( string $bucket, string $groupKey, bool $includeLandingRefresh = false ) :array {
		$actionData = [
			'bucket' => $bucket,
			'group'  => $groupKey,
		];
		if ( $includeLandingRefresh ) {
			$actionData[ 'include_landing_refresh' ] = 1;
		}

		return $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, $actionData );
	}

	private function enableAssetScanFixture( array $scanAreas ) :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', $scanAreas )
			 ->store();

		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();
	}

	private function disableCriticalScanLanesFixture() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'N' )
			 ->optSet( 'enable_wpvuln_scan', 'N' )
			 ->optSet( 'enabled_scan_apc', 'N' )
			 ->optSet( 'file_scan_areas', [] )
			 ->optSet( 'file_locker', [] )
			 ->store();
		$this->resetScanResultCountMemoization();
	}

	private function seedCriticalAssetAndVulnerabilityQueue() :void {
		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );
		$this->resetScanResultCountMemoization();
	}

	private function pluginMainPathFragment( ?string $pluginSlug = null ) :string {
		return TestDataFactory::afsFileItemIdFromPath( WP_PLUGIN_DIR.'/'.( $pluginSlug ?? self::con()->base_file ) );
	}

	private function themeMainPathFragment( ?string $themeSlug = null ) :string {
		$themeSlug = $themeSlug ?? $this->requireAtLeastInstalledThemes( 1 )[ 0 ];
		return TestDataFactory::afsFileItemIdFromPath( \get_theme_root().'/'.$themeSlug.'/style.css' );
	}

	private function ensureFixtureFileExists( string $absolutePath ) :void {
		$absolutePath = \wp_normalize_path( $absolutePath );
		if ( \is_file( $absolutePath ) ) {
			return;
		}

		$dir = \dirname( $absolutePath );
		if ( !\is_dir( $dir ) ) {
			$this->assertTrue( \wp_mkdir_p( $dir ) );
			$this->tempDirs[] = $dir;
		}
		$this->assertNotFalse( \file_put_contents( $absolutePath, "fixture\n" ) );
		$this->assertFileExists( $absolutePath );
		$this->tempPaths[] = $absolutePath;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function retrieveScanResultsTableData(
		string $type,
		string $file,
		?array $resultsDisplayOptions = null,
		bool $actionsQueueNoticeContext = true,
		?array $tableData = null
	) :array {
		$snapshot = $this->seedActionNonceContext( ScanResultsTableAction::class );
		$displayOptions = new ScanResultsDisplayOptions();
		$actionData = [
			'sub_action' => 'retrieve_table_data',
			'type'       => $type,
			'file'       => $file,
			'table_data' => $tableData ?? [
				'search'  => [ 'value' => '' ],
				'start'   => 0,
				'length'  => 100,
				'order'   => [],
				'columns' => [],
			],
			...$displayOptions->buildExplicitActionData( $resultsDisplayOptions ?? $displayOptions->activeOnly() ),
		];
		if ( $actionsQueueNoticeContext ) {
			$actionData[ 'scan_results_notice_context' ] = ActionsQueueScanResultScopeStateBuilder::NOTICE_CONTEXT_ACTIONS_QUEUE;
		}

		try {
			$payload = $this->processActionPayloadWithAdminBypass( ScanResultsTableAction::SLUG, $actionData );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertIsArray( $payload[ 'datatable_data' ] ?? null );

		return $payload[ 'datatable_data' ];
	}

	/**
	 * @param list<int> $expectedActiveIds
	 * @param list<int> $expectedIgnoredIds
	 */
	private function assertScopedScanResultsDisplayMatrix(
		string $type,
		string $file,
		array $expectedActiveIds,
		array $expectedIgnoredIds
	) :void {
		$displayOptions = new ScanResultsDisplayOptions();
		$ignoredCount = \count( $expectedIgnoredIds );

		$active = $this->retrieveScanResultsTableData( $type, $file, $displayOptions->activeOnly() );
		$this->assertScanResultsRows( $active, $expectedActiveIds, 'active-only rows for '.$type.':'.$file );
		$this->assertDisplayNotice(
			$active,
			$ignoredCount > 0
				? ActionsQueueScanResultScopeStateBuilder::MODE_HIDDEN_IGNORED
				: ActionsQueueScanResultScopeStateBuilder::MODE_NONE,
			$ignoredCount
		);

		$ignored = $this->retrieveScanResultsTableData( $type, $file, $displayOptions->ignoredOnly() );
		$this->assertScanResultsRows( $ignored, $expectedIgnoredIds, 'ignored-only rows for '.$type.':'.$file );
		$this->assertDisplayNotice(
			$ignored,
			$ignoredCount > 0
				? ActionsQueueScanResultScopeStateBuilder::MODE_SHOWING_IGNORED
				: ActionsQueueScanResultScopeStateBuilder::MODE_NONE,
			$ignoredCount
		);

		$includingIgnored = $this->retrieveScanResultsTableData( $type, $file, $displayOptions->activeAndIgnored() );
		$this->assertScanResultsRows(
			$includingIgnored,
			\array_merge( $expectedActiveIds, $expectedIgnoredIds ),
			'include-ignored rows for '.$type.':'.$file
		);
		$this->assertDisplayNotice(
			$includingIgnored,
			$ignoredCount > 0
				? ActionsQueueScanResultScopeStateBuilder::MODE_INCLUDING_IGNORED
				: ActionsQueueScanResultScopeStateBuilder::MODE_NONE,
			$ignoredCount
		);
	}

	/**
	 * @param list<int> $expectedIds
	 */
	private function assertScanResultsRows( array $datatableData, array $expectedIds, string $context ) :void {
		$this->assertSame( \count( $expectedIds ), (int)( $datatableData[ 'recordsTotal' ] ?? -1 ), $context );
		$this->assertSame( \count( $expectedIds ), (int)( $datatableData[ 'recordsFiltered' ] ?? -1 ), $context );
		$this->assertEqualsCanonicalizing(
			$expectedIds,
			\array_map( 'intval', \array_column( $datatableData[ 'data' ] ?? [], 'rid' ) ),
			$context
		);
	}

	private function assertDisplayNotice( array $datatableData, string $expectedMode, int $expectedIgnoredCount ) :void {
		$this->assertArrayHasKey( 'display_notice', $datatableData );
		$this->assertIsArray( $datatableData[ 'display_notice' ] );
		$notice = $datatableData[ 'display_notice' ];

		$this->assertSame( $expectedMode, (string)$notice[ 'mode' ] );
		$this->assertSame( $expectedMode !== ActionsQueueScanResultScopeStateBuilder::MODE_NONE, (bool)$notice[ 'is_visible' ] );
		$this->assertSame(
			$expectedMode === ActionsQueueScanResultScopeStateBuilder::MODE_NONE ? 0 : $expectedIgnoredCount,
			(int)$notice[ 'ignored_count' ]
		);
	}

	private function assertSelectedGroupHasIgnoredOnlyRailContext( string $bucket, string $groupKey, int $expectedIgnoredCount ) :void {
		$payload = $this->loadSelectedGroupPayload( $bucket, $groupKey );
		$this->assertSame( $groupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $payload[ 'selected_group' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertStringContainsString(
			'ignored',
			\strtolower( (string)( $payload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) )
		);
		$this->assertStringContainsString(
			(string)$expectedIgnoredCount,
			(string)( $payload[ 'selected_group' ][ 'header' ][ 'focus' ] ?? '' )
		);
		$this->assertStringContainsString(
			'Display Results',
			(string)( $payload[ 'selected_group' ][ 'header' ][ 'next_step' ] ?? '' )
		);
	}

	private function ignoreAllMalwareResults() :array {
		$snapshot = $this->seedActionNonceContext( ScanResultsTableAction::class );

		try {
			return $this->processActionPayloadWithAdminBypass( ScanResultsTableAction::SLUG, [
				'sub_action' => 'ignore_all',
				'type'       => 'malware',
				'file'       => 'malware',
			] );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	private function prepareFileLockerRuntime( array $lockTypes = [ 'wpconfig' ] ) {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
		RuntimeTestState::primeShieldNetHandshake();
		$this->requireController()->opts
			 ->optSet( 'file_locker', $lockTypes )
			 ->store();

		$handler = $this->requireDb( 'file_locker' );
		self::con()->comps->file_locker->clearLocks();

		return $handler;
	}

	/**
	 * @param array<string,mixed> $header
	 * @param array{type:string,file:string} $expectedScope
	 */
	private function assertIgnoreAllHeaderAction( array $header, array $expectedScope ) :void {
		$this->assertCount( 1, $header[ 'actions' ] ?? [] );
		$actionData = \json_decode( (string)( $header[ 'actions' ][ 0 ][ 'ajax_action_json' ] ?? '' ), true );
		$this->assertIsArray( $actionData );
		$this->assertSame( 'ignore_all', (string)( $actionData[ 'sub_action' ] ?? '' ) );
		$this->assertSame( $expectedScope[ 'type' ], (string)( $actionData[ 'type' ] ?? '' ) );
		$this->assertSame( $expectedScope[ 'file' ], (string)( $actionData[ 'file' ] ?? '' ) );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			(array)( $actionData[ 'results_display_options' ] ?? [] )
		);
	}

	private function assertHeaderHasNoDisplayOptions( array $header ) :void {
		$this->assertArrayNotHasKey( 'display_options', $header );
	}

	private function assertGroupResultsDisplayOptions( array $selectedGroup, array $expectedOptions ) :void {
		$this->assertSame(
			$expectedOptions,
			(array)( $selectedGroup[ 'detail_render_action' ][ 'results_display_options' ] ?? [] )
		);
	}

	/**
	 * @param array{type:string,file:string} $expectedScope
	 */
	private function assertDirectTableGroupAndDetailExposeIgnoreAllAction(
		string $bucket,
		string $groupKey,
		array $expectedScope
	) :void {
		$groupsPayload = $this->loadSelectedGroupPayload( $bucket, $groupKey );
		$this->assertSame( $groupKey, (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertIgnoreAllHeaderAction( (array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ), $expectedScope );
		$this->assertHeaderHasNoDisplayOptions( (array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ) );
		$this->assertGroupResultsDisplayOptions(
			(array)( $groupsPayload[ 'selected_group' ] ?? [] ),
			( new ScanResultsDisplayOptions() )->activeOnly()
		);
		$this->assertSame( 'ajax_render', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'ex' ] ?? '' ) );
	}

	private function renderSelectedGroupDetail( array $groupsPayload ) :array {
		$renderAction = \is_array( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ] ?? null )
			? $groupsPayload[ 'selected_group' ][ 'detail_render_action' ]
			: [];
		$renderSlug = (string)( $renderAction[ 'render_slug' ] ?? '' );
		$this->assertNotSame( '', $renderSlug );

		return $this->processActionPayloadWithAdminBypass( $renderSlug, $renderAction );
	}

	private function assertDisabledDirectScanRenderPayload( array $payload, string $context ) :void {
		$this->assertRouteRenderOutputHealthy( $payload, $context );
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'is_disabled' ] ?? false ), $context );
		$this->assertSame( [], $renderData[ 'table' ] ?? [ 'unexpected' ], $context );
	}

	private function assertDisabledAssetCardsRenderPayload( array $payload, string $context ) :void {
		$this->assertRouteRenderOutputHealthy( $payload, $context );
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'is_disabled' ] ?? false ), $context );
		$this->assertSame( [], $renderData[ 'vars' ][ 'asset_cards' ] ?? [ 'unexpected' ], $context );
	}

	private function assertDisabledRailPaneRenderPayload( array $payload, string $context ) :void {
		$this->assertRouteRenderOutputHealthy( $payload, $context );
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$tab = \is_array( $renderData[ 'tab' ] ?? null ) ? $renderData[ 'tab' ] : [];

		$this->assertTrue( (bool)( $tab[ 'is_disabled' ] ?? false ), $context );
		$this->assertSame( [], $tab[ 'items' ] ?? [ 'unexpected' ], $context );
		$this->assertSame( 0, (int)( $tab[ 'count_items' ] ?? -1 ), $context );
	}

	private function findZoneTile( array $zoneTiles, string $key ) :array {
		$matches = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $key
		) );
		$this->assertCount( 1, $matches, 'Expected exactly one zone tile for '.$key );
		return $matches[ 0 ] ?? [];
	}

	/**
	 * @return array<string,array{count:int,status:string}>
	 */
	private function buildActionsQueueGroupMetrics() :array {
		$assessmentBuilder = new ActionsQueueLandingAssessmentBuilder();
		$assessmentRowsByZone = [
			'scans'       => $assessmentBuilder->buildForZone( 'scans' ),
			'maintenance' => $assessmentBuilder->buildForZone( 'maintenance' ),
		];
		$groupsBuilder = new ActionsQueueGroupsBuilder();
		$groups = [];

		foreach ( [ 'critical', 'review' ] as $bucketKey ) {
			$layer = $groupsBuilder->build(
				$bucketKey,
				self::con()->comps->site_query->attention(),
				$assessmentRowsByZone
			);
			foreach ( \is_array( $layer[ 'active_sections' ] ?? null ) ? $layer[ 'active_sections' ] : [] as $section ) {
				foreach ( \is_array( $section[ 'groups' ] ?? null ) ? $section[ 'groups' ] : [] as $group ) {
					$key = (string)( $group[ 'key' ] ?? '' );
					if ( $key === '' ) {
						continue;
					}
					$groups[ $key ] = [
						'count'  => (int)( $group[ 'item_count' ] ?? 0 ),
						'status' => (string)( $group[ 'status' ] ?? '' ),
					];
				}
			}
		}

		return $groups;
	}

	/**
	 * @param array<string,array{count:int,status:string}> $groups
	 */
	private function groupCountForPrefix( array $groups, string $prefix ) :int {
		$count = 0;
		foreach ( $groups as $key => $group ) {
			if ( \str_starts_with( $key, $prefix ) ) {
				$count += $group[ 'count' ];
			}
		}

		return $count;
	}

	/**
	 * @param array<string,array{count:int,status:string}> $groups
	 * @return list<string>
	 */
	private function groupStatusesForPrefix( array $groups, string $prefix ) :array {
		$statuses = [];
		foreach ( $groups as $key => $group ) {
			if ( \str_starts_with( $key, $prefix ) ) {
				$statuses[] = $group[ 'status' ];
			}
		}

		return \array_values( \array_unique( $statuses ) );
	}

	/**
	 * @param array<string,array{count:int,status:string}> $groups
	 * @return list<string>
	 */
	private function groupKeysForPrefix( array $groups, string $prefix ) :array {
		return \array_values( \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, $prefix )
		) );
	}

	public function test_actions_queue_landing_keeps_drill_shell_without_removed_all_clear_box_when_queue_is_empty() :void {
		$optionsSnapshot = $this->snapshotSelectedOptions( [ MaintenanceIssueStateProvider::OPT_KEY ] );
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		try {
			$this->requireController()->opts
				 ->optSet(
					 MaintenanceIssueStateProvider::OPT_KEY,
					 ( new MaintenanceIssueStateProvider() )->currentIssueIdentifiersByKey()
				 )
				 ->store();

			$payload = $this->renderActionsQueueLandingPage();
			$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
			$renderData = $payload[ 'render_data' ] ?? [];
			$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
			$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

			$this->assertModeShellPayload( $vars, 'actions', 'actions', false );
			$this->assertModePanelPayload( $vars, '', false );
			$this->assertSame( 'good', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge_status' ] ?? '' ) );
			$this->assertIsString( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? null );
			$this->assertCount( 2, $zoneTiles );
			$this->assertCount(
				2,
				\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
			);
			$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'scans' )[ 'assessment_rows' ] ?? [] );
			$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'maintenance' )[ 'assessment_rows' ] ?? [] );
			$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
			$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
			$this->assertNotEmpty( $vars[ 'actions_queue_ajax' ] ?? [] );
		}
		finally {
			$this->restoreSelectedOptions( $optionsSnapshot );
		}
	}

	public function test_actions_queue_landing_renders_drill_shell_and_bucket_cards_when_queue_has_items() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );

		$this->assertModeShellPayload( $vars, 'actions', 'actions', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? '' ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertTrue( (bool)( $maintenance[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $maintenance[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 'maintenance', (string)( $maintenance[ 'panel_target' ] ?? '' ) );
		$this->assertNotEmpty( $maintenance[ 'assessment_rows' ] ?? [] );
		$maintenanceDetailGroups = $maintenance[ 'maintenance_detail_groups' ] ?? [];
		$this->assertSame( [ 'warning', 'good' ], \array_column( $maintenanceDetailGroups, 'status' ) );
		$this->assertNotEmpty( $maintenanceDetailGroups[ 0 ][ 'rows' ] ?? [] );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $scans[ 'has_issues' ] ?? true ) );
		$this->assertNotEmpty( $scans[ 'assessment_rows' ] ?? [] );
		$this->assertArrayNotHasKey( 'groups_render_action', $vars[ 'actions_queue_ajax' ] );
		$this->assertSame(
			ActionsQueueDrillDownGroups::SLUG,
			(string)( \json_decode( (string)( $vars[ 'actions_queue_ajax' ][ 'groups_render_action_json' ] ?? '' ), true )[ 'render_slug' ] ?? '' )
		);
	}

	public function test_maintenance_panel_exposes_updates_href() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance ctas' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$maintenance = $this->findZoneTile( \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [], 'maintenance' );
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertNotSame( '', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'cta' ][ 'href' ] ?? '' ) );
		$this->assertSame(
			self::con()->plugin_urls->actionsQueueScans(),
			(string)( $renderData[ 'hrefs' ][ 'scan_results' ] ?? '' )
		);
		$this->assertSame( Services::WpGeneral()->getAdminUrl_Updates(), (string)( $renderData[ 'hrefs' ][ 'wp_updates' ] ?? '' ) );
	}

	public function test_maintenance_panel_inactive_plugin_rows_link_to_filtered_plugins_screen() :void {
		$pluginFile = $this->requireAtLeastInactivePlugins( 1 )[ 0 ];

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing inactive plugin links' );
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$maintenance = $this->findZoneTile( \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [], 'maintenance' );
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}
		$inactiveRows = \is_array( $itemsByKey[ 'wp_plugins_inactive' ][ 'expansion' ][ 'table' ][ 'rows' ] ?? null )
			? $itemsByKey[ 'wp_plugins_inactive' ][ 'expansion' ][ 'table' ][ 'rows' ]
			: [];
		$matchingRows = \array_values( \array_filter(
			$inactiveRows,
			static fn( array $row ) :bool => (string)( $row[ 'identifier' ] ?? '' ) === $pluginFile
		) );

		$this->assertCount( 1, $matchingRows );
		$this->assertSame( DetailExpansionType::SIMPLE_TABLE, (string)( $itemsByKey[ 'wp_plugins_inactive' ][ 'expansion' ][ 'type' ] ?? '' ) );
		$this->assertSame(
			(string)( $matchingRows[ 0 ][ 'action' ][ 'label' ] ?? '' ),
			(string)( $matchingRows[ 0 ][ 'action' ][ 'tooltip' ] ?? '' )
		);
		$this->assertTrue( (bool)( $matchingRows[ 0 ][ 'action' ][ 'is_icon_only' ] ?? false ) );
		$this->assertSame( '', (string)( $matchingRows[ 0 ][ 'action' ][ 'target' ] ?? '' ) );
		$actionHref = (string)( $matchingRows[ 0 ][ 'action' ][ 'href' ] ?? '' );
		$this->assertSame( '/wp-admin/plugins.php', (string)( \wp_parse_url( $actionHref, \PHP_URL_PATH ) ?? '' ) );
		$queryArgs = [];
		\parse_str( (string)( \wp_parse_url( $actionHref, \PHP_URL_QUERY ) ?? '' ), $queryArgs );
		$this->assertArrayHasKey( 's', $queryArgs );
		$this->assertNotSame( 'activate', (string)( $queryArgs[ 'action' ] ?? '' ) );
	}

	public function test_groups_ajax_returns_bucket_groups_and_context() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );

		$this->assertSame(
			(string)( $payload[ 'bucket_selection' ][ 'label' ] ?? '' ),
			(string)( $payload[ 'header' ][ 'title' ] ?? '' )
		);
		$this->assertSame( 'warning', (string)( $payload[ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'active_sections', $payload );
		$this->assertArrayNotHasKey( 'healthy_sections', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertArrayNotHasKey( 'render_data', $payload );
		$this->assertArrayNotHasKey( 'render_output', $payload );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
	}

	public function test_groups_ajax_can_refresh_the_current_selected_group_summary() :void {
		$this->setPluginUpdateAvailable();
		$selectedGroupKey = 'wp_plugins_updates';

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket'                  => 'review',
			'group'                   => $selectedGroupKey,
			'include_landing_refresh' => 1,
		] );

		$this->assertSame( $selectedGroupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'maintenance', (string)( $payload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			(string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ),
			(string)( $payload[ 'selected_group' ][ 'header' ][ 'title' ] ?? '' )
		);
		$this->assertSame( 1, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? 0 ) );
		$this->assertArrayNotHasKey( 'queue_is_empty', $payload[ 'landing_refresh' ] ?? [] );
		$this->assertTrue( (bool)( $payload[ 'landing_refresh' ][ 'has_drilldown_content' ] ?? false ) );
		$rootStep = \json_decode( (string)( $payload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ), true );
		$this->assertSame( 'actions', (string)( $rootStep[ 'color_key' ] ?? '' ) );
		$this->assertArrayHasKey( 'buckets_html', $payload[ 'landing_refresh' ] ?? [] );
		$this->assertIsString( $payload[ 'landing_refresh' ][ 'buckets_html' ] );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertHeaderHasNoDisplayOptions( (array)( $payload[ 'selected_group' ][ 'header' ] ?? [] ) );
	}

	public function test_fully_ignored_plugin_results_do_not_create_actions_queue_group() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;
		$pathFragment = $this->pluginMainPathFragment( $pluginSlug );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$ignoredIds = [];
		foreach ( [ 1, 2, 3, 4 ] as $_ ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $pathFragment, [
				'is_in_plugin'    => 1,
				'is_unrecognised' => 1,
				'ptg_slug'        => $pluginSlug,
			] );
			$ignoredIds[] = (int)$tracked[ 'result_item_id' ];
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$attentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertNotContains( 'plugin_files', $attentionKeys );
		$this->assertSame( 0, $this->groupCountForPrefix( $groups, 'plugins:' ) );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'plugins:'.$pluginSlug, 4 );
		$this->assertScopedScanResultsDisplayMatrix( 'plugin', $pluginSlug, [], $ignoredIds );
	}

	public function test_fully_ignored_wordpress_results_do_not_create_actions_queue_group() :void {
		$this->enableAssetScanFixture( [ 'wp' ] );

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$ignoredIds = [];
		foreach ( [ 'wp-admin/admin.php', 'wp-includes/version.php' ] as $pathFragment ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $pathFragment, [
				'is_in_core' => 1,
			] );
			$ignoredIds[] = (int)$tracked[ 'result_item_id' ];
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$attentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertNotContains( 'wp_files', $attentionKeys );
		$this->assertArrayNotHasKey( 'wordpress', $groups );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'wordpress', 2 );
		$this->assertScopedScanResultsDisplayMatrix( 'wordpress', 'wordpress', [], $ignoredIds );
	}

	public function test_wordpress_direct_table_group_and_detail_expose_ignore_all_action() :void {
		$this->enableAssetScanFixture( [ 'wp' ] );

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$this->assertDirectTableGroupAndDetailExposeIgnoreAllAction(
			'critical',
			'wordpress',
			[
				'type' => 'wordpress',
				'file' => 'wordpress',
			]
		);
	}

	public function test_theme_direct_table_group_and_detail_expose_ignore_all_action() :void {
		$this->enableAssetScanFixture( [ 'themes' ] );

		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$this->assertDirectTableGroupAndDetailExposeIgnoreAllAction(
			'critical',
			'themes:'.$themeSlug,
			[
				'type' => 'theme',
				'file' => $themeSlug,
			]
		);
	}

	public function test_fully_ignored_theme_results_do_not_create_actions_queue_group() :void {
		$this->enableAssetScanFixture( [ 'themes' ] );
		$this->ensureInstalledThemeFixture();

		$themeSlug = self::THEME_FIXTURE_STYLESHEET;
		$this->assertContains( $themeSlug, Services::WpThemes()->getInstalledStylesheets() );
		$themePathFull = \wp_normalize_path( $this->themeFixtureStylePath() );
		$pathFragment = TestDataFactory::afsFileItemIdFromPath( $themePathFull );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$trackedRows = [];
		foreach ( [ 1, 2 ] as $_ ) {
			$trackedRows[] = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $pathFragment, [
				'is_in_theme'     => 1,
				'is_unrecognised' => 1,
				'ptg_slug'        => $themeSlug,
			] );
		}
		$this->resetScanResultCountMemoization();

		$preIgnoreAttentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$preIgnoreGroups = $this->buildActionsQueueGroupMetrics();

		$this->assertContains( 'theme_files', $preIgnoreAttentionKeys );
		$this->assertSame( 2, $this->groupCountForPrefix( $preIgnoreGroups, 'themes:' ) );

		$ignoredIds = [];
		foreach ( $trackedRows as $tracked ) {
			$ignoredIds[] = (int)$tracked[ 'result_item_id' ];
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$attentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertNotContains( 'theme_files', $attentionKeys );
		$this->assertSame( 0, $this->groupCountForPrefix( $groups, 'themes:' ) );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'themes:'.$themeSlug, 2 );
		$this->assertScopedScanResultsDisplayMatrix( 'theme', $themeSlug, [], $ignoredIds );
	}

	public function test_malware_direct_table_group_and_detail_expose_ignore_all_action() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );
		$this->resetScanResultCountMemoization();

		$this->assertDirectTableGroupAndDetailExposeIgnoreAllAction(
			'critical',
			'malware',
			[
				'type' => 'malware',
				'file' => 'malware',
			]
		);
	}

	public function test_selected_malware_group_refresh_after_ignore_all_has_zero_items_and_no_context_actions() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResultTracked( $afsId, 'wp-content/uploads/active-malware.php', [
			'is_mal' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$activePayload = $this->loadSelectedGroupPayload( 'critical', 'malware' );
		$this->assertSame( 1, (int)( $activePayload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertIgnoreAllHeaderAction( (array)( $activePayload[ 'selected_group' ][ 'header' ] ?? [] ), [
			'type' => 'malware',
			'file' => 'malware',
		] );

		$ignorePayload = $this->ignoreAllMalwareResults();
		$this->assertTrue( $ignorePayload[ 'success' ] ?? false );
		$this->assertFalse( $ignorePayload[ 'page_reload' ] ?? true );
		$this->assertTrue( $ignorePayload[ 'table_reload' ] ?? false );

		$refreshPayload = $this->loadSelectedGroupPayload( 'critical', 'malware', true );

		$this->assertSame( 'malware', (string)( $refreshPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $refreshPayload[ 'selected_group' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $refreshPayload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertSame( [], $refreshPayload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertStringContainsString(
			'ignored',
			\strtolower( (string)( $refreshPayload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) )
		);
	}

	public function test_fully_ignored_malware_results_do_not_create_actions_queue_group() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$ignoredIds = [];
		foreach ( [ 'ignored-malware-a.php', 'ignored-malware-b.php' ] as $itemId ) {
			$pathFragment = 'wp-content/uploads/'.$itemId;
			$this->ensureFixtureFileExists( ABSPATH.$pathFragment );
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $pathFragment, [
				'is_mal' => 1,
			] );
			$ignoredIds[] = (int)$tracked[ 'result_item_id' ];
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$attentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertNotContains( 'malware', $attentionKeys );
		$this->assertArrayNotHasKey( 'malware', $groups );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'malware', 2 );
		$this->assertScopedScanResultsDisplayMatrix( 'malware', 'malware', [], $ignoredIds );
	}

	public function test_active_plugin_group_ignores_ignored_only_companions_in_actions_queue_count() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$pathFragment = $this->pluginMainPathFragment( $pluginSlug );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$active = TestDataFactory::insertAfsFileScanResultTracked(
			$afsId,
			$pathFragment,
			[
				'is_in_plugin'    => 1,
				'is_unrecognised' => 1,
				'ptg_slug'        => $pluginSlug,
			]
		);
		$ignoredIds = [];
		foreach ( [ 1, 2 ] as $_ ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked(
				$afsId,
				$pathFragment,
				[
					'is_in_plugin'    => 1,
					'is_unrecognised' => 1,
					'ptg_slug'        => $pluginSlug,
				]
			);
			$ignoredIds[] = (int)$tracked[ 'result_item_id' ];
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$groupsPayload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
			'group'  => 'plugins:'.$pluginSlug,
		] );
		$this->assertSame( 'plugins:'.$pluginSlug, (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame( 1, (int)( $groupsPayload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertIgnoreAllHeaderAction( (array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ), [
			'type' => 'plugin',
			'file' => $pluginSlug,
		] );
		$this->assertHeaderHasNoDisplayOptions( (array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ) );
		$this->assertStringContainsString(
			'ignored',
			\strtolower( (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) )
		);
		$this->assertStringContainsString(
			'ignored',
			\strtolower( (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'focus' ] ?? '' ) )
		);
		$this->assertSame(
			( new ScanResultsDisplayOptions() )->activeOnly(),
			(array)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'results_display_options' ] ?? [] )
		);
		$this->assertSame( 'actions_queue_asset_file_status_detail', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );
		$this->assertSame( 'actions_queue', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'display_context' ] ?? '' ) );
		$this->assertScopedScanResultsDisplayMatrix(
			'plugin',
			$pluginSlug,
			[ (int)$active[ 'result_item_id' ] ],
			$ignoredIds
		);
	}

	public function test_historical_non_active_plugin_rows_do_not_create_actions_queue_work() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;
		$pluginPathFragment = $this->pluginMainPathFragment( $pluginSlug );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$trackedRows = [];
		foreach ( [
			'ignored',
			'repaired',
			'deleted',
			'auto_filtered',
			'clean_rescan',
			'asset_replaced',
		] as $stateKey ) {
			$trackedRows[ $stateKey ] = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $pluginPathFragment, [
				'is_in_plugin'    => 1,
				'is_unrecognised' => 1,
				'ptg_slug'        => $pluginSlug,
			] );
		}

		TestDataFactory::markScanResultItemIgnored( (int)$trackedRows[ 'ignored' ][ 'result_item_id' ] );
		TestDataFactory::markScanResultItemResolved( (int)$trackedRows[ 'repaired' ][ 'result_item_id' ], 'repaired' );
		TestDataFactory::markScanResultItemResolved( (int)$trackedRows[ 'deleted' ][ 'result_item_id' ], 'deleted' );
		TestDataFactory::markScanResultItemAutoFiltered( (int)$trackedRows[ 'auto_filtered' ][ 'result_item_id' ] );
		TestDataFactory::markScanResultItemResolved( (int)$trackedRows[ 'clean_rescan' ][ 'result_item_id' ], 'clean_rescan' );
		TestDataFactory::markScanResultItemResolved( (int)$trackedRows[ 'asset_replaced' ][ 'result_item_id' ], 'asset_replaced' );
		$this->resetScanResultCountMemoization();

		$attentionKeys = \array_column( self::con()->comps->site_query->attention()[ 'items' ] ?? [], 'key' );
		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertNotContains( 'plugin_files', $attentionKeys );
		$this->assertSame( 0, $this->groupCountForPrefix( $groups, 'plugins:' ) );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'plugins:'.$pluginSlug, 1 );
		$this->assertScopedScanResultsDisplayMatrix(
			'plugin',
			$pluginSlug,
			[],
			[ (int)$trackedRows[ 'ignored' ][ 'result_item_id' ] ]
		);
	}

	public function test_stale_active_row_cleanup_refreshes_group_to_good_while_preserving_ignored_notice() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $afsId, 'wp-content/uploads/missing-stale-malware.php', [
			'is_mal'          => 1,
			'is_unrecognised' => 1,
		] );
		$ignored = TestDataFactory::insertAfsFileScanResultTracked( $afsId, 'wp-content/uploads/ignored-malware.php', [
			'is_mal' => 1,
		] );
		$this->ensureFixtureFileExists( ABSPATH.'wp-content/uploads/ignored-malware.php' );
		TestDataFactory::markScanResultItemIgnored( (int)$ignored[ 'result_item_id' ] );
		$this->resetScanResultCountMemoization();

		$beforeCleanup = $this->loadSelectedGroupPayload( 'critical', 'malware' );
		$this->assertSame( 1, (int)( $beforeCleanup[ 'selected_group' ][ 'item_count' ] ?? -1 ) );

		$activeTable = $this->retrieveScanResultsTableData( 'malware', 'malware', ( new ScanResultsDisplayOptions() )->activeOnly() );

		$this->assertTrue( (bool)( $activeTable[ 'scan_results_changed' ] ?? false ) );
		$this->assertScanResultsRows( $activeTable, [], 'stale active malware row cleaned during table load' );
		$this->assertDisplayNotice(
			$activeTable,
			ActionsQueueScanResultScopeStateBuilder::MODE_HIDDEN_IGNORED,
			1
		);
		$this->assertScopedScanResultsDisplayMatrix(
			'malware',
			'malware',
			[],
			[ (int)$ignored[ 'result_item_id' ] ]
		);

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $item );
		$this->assertSame( 'asset_replaced', (string)( $item->resolution_reason ?? '' ) );
		$this->assertSelectedGroupHasIgnoredOnlyRailContext( 'critical', 'malware', 1 );
	}

	public function test_healthy_file_locker_is_visible_on_landing_and_in_critical_healthy_stack() :void {
		$this->prepareFileLockerRuntime();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->renderActionsQueueLandingPage();
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$fileLockerAssessments = \array_values( \array_filter(
			\is_array( $scans[ 'assessment_rows' ] ?? null ) ? $scans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerAssessments );
		$this->assertSame( 'good', (string)( $fileLockerAssessments[ 0 ][ 'status' ] ?? '' ) );

		$groupsPayload = $this->loadSelectedGroupPayload( 'critical', 'file_locker' );
		$this->assertSame( 'file_locker', (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'asset_cards', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame( 0, (int)( $groupsPayload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertSame( 'actions_queue', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'display_context' ] ?? '' ) );
		$this->assertSame( FileLockerPane::SLUG, (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );
	}

	public function test_file_locker_warning_clears_on_landing_immediately_after_reassessment() :void {
		$handler = $this->prepareFileLockerRuntime();

		$tempPath = \tempnam( \sys_get_temp_dir(), 'shield-file-locker-landing-' );
		$this->assertIsString( $tempPath );
		$this->tempPaths[] = $tempPath;
		$this->assertTrue( Services::WpFs()->putFileContent( $tempPath, 'original-file-content' ) );

		$record = $handler->getRecord();
		$record->type = 'wpconfig';
		$record->path = $tempPath;
		$record->hash_original = \sha1( 'original-file-content' );
		$record->hash_current = \sha1( 'stale-different-content' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-wpconfig';
		$record->detected_at = \time() - 60;
		$handler->getQueryInserter()->insert( $record );

		self::con()->comps->file_locker->clearLocks();

		$warningPayload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $warningPayload, 'actions queue landing file locker warning state' );
		$warningVars = \is_array( $warningPayload[ 'render_data' ][ 'vars' ] ?? null )
			? $warningPayload[ 'render_data' ][ 'vars' ]
			: [];
		$warningScans = $this->findZoneTile(
			\is_array( $warningVars[ 'zone_tiles' ] ?? null )
				? $warningVars[ 'zone_tiles' ]
				: [],
			'scans'
		);
		$fileLockerWarningAssessments = \array_values( \array_filter(
			\is_array( $warningScans[ 'assessment_rows' ] ?? null ) ? $warningScans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerWarningAssessments );
		$this->assertSame( 'critical', (string)( $fileLockerWarningAssessments[ 0 ][ 'status' ] ?? '' ) );

		self::con()->comps->file_locker->reassessLocksNow();

		$healthyPayload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $healthyPayload, 'actions queue landing file locker healthy state' );
		$healthyVars = \is_array( $healthyPayload[ 'render_data' ][ 'vars' ] ?? null )
			? $healthyPayload[ 'render_data' ][ 'vars' ]
			: [];
		$healthyScans = $this->findZoneTile(
			\is_array( $healthyVars[ 'zone_tiles' ] ?? null )
				? $healthyVars[ 'zone_tiles' ]
				: [],
			'scans'
		);
		$fileLockerHealthyAssessments = \array_values( \array_filter(
			\is_array( $healthyScans[ 'assessment_rows' ] ?? null ) ? $healthyScans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerHealthyAssessments );
		$this->assertSame( 'good', (string)( $fileLockerHealthyAssessments[ 0 ][ 'status' ] ?? '' ) );
	}

	public function test_scans_assessment_rows_include_plugin_and_theme_files_only_when_asset_scan_gates_are_satisfied() :void {
		$this->requireController()->opts->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] );
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$baselinePayload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $baselinePayload, 'actions queue landing asset scan checklist baseline' );
		$baselineScans = $this->findZoneTile(
			\is_array( $baselinePayload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ] ?? null )
				? $baselinePayload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ]
				: [],
			'scans'
		);
		$baselineKeys = \array_column( $baselineScans[ 'assessment_rows' ] ?? [], 'key' );
		$this->assertNotContains( 'plugin_files', $baselineKeys );
		$this->assertNotContains( 'theme_files', $baselineKeys );

		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing asset scan checklist gated' );
		$scans = $this->findZoneTile(
			\is_array( $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ] ?? null )
				? $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ]
				: [],
			'scans'
		);
		$assessmentKeys = \array_column( $scans[ 'assessment_rows' ] ?? [], 'key' );

		$this->assertContains( 'plugin_files', $assessmentKeys );
		$this->assertContains( 'theme_files', $assessmentKeys );
	}

	public function test_wordpress_disabled_fix_now_group_uses_neutral_header_and_settings_cta() :void {
		$this->disableCriticalScanLanesFixture();

		$groupsPayload = $this->loadSelectedGroupPayload( 'critical', 'wordpress' );

		$this->assertSame( 'wordpress', (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $groupsPayload[ 'selected_group' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertNotSame( '', \trim( (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) ) );
		$this->assertSame( [], (array)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? [] ) );
		$this->assertHeaderHasNoDisplayOptions( (array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ) );
	}

	public function test_file_locker_disabled_fix_now_group_uses_upgrade_cta() :void {
		$this->disableCriticalScanLanesFixture();

		$groupsPayload = $this->loadSelectedGroupPayload( 'critical', 'file_locker' );

		$this->assertSame( 'file_locker', (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $groupsPayload[ 'selected_group' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertNotSame( '', \trim( (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) ) );
	}

	public function test_actions_queue_scan_groups_return_exact_counts_for_enabled_sources() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes', 'malware_php' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'wp-admin/admin.php',
			'is_in_core'   => 1,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );

		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertSame( 1, (int)( $groups[ 'wordpress' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $groups[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 2, $this->groupCountForPrefix( $groups, 'plugins:' ) );
		$this->assertSame( [ 'critical' ], $this->groupStatusesForPrefix( $groups, 'plugins:' ) );
		$this->assertSame( 1, $this->groupCountForPrefix( $groups, 'themes:' ) );
		$this->assertSame( [ 'critical' ], $this->groupStatusesForPrefix( $groups, 'themes:' ) );
		$this->assertSame( 1, (int)( $groups[ 'malware' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $groups[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'file_locker', $groups );

		$this->assertCount( 1, \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, 'vulnerabilities:' )
		) );
		$this->assertCount( 1, \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, 'abandoned:' )
		) );
	}

	public function test_notified_vulnerabilities_remain_visible_with_asset_scan_results_in_actions_queue() :void {
		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		$vulnerability = TestDataFactory::insertScanResultItemTracked( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );
		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById(
			(int)$vulnerability[ 'result_item_id' ],
			[ 'notified_at' => Services::Request()->ts() - 60 ]
		);

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$groups = $this->buildActionsQueueGroupMetrics();
		$vulnerabilityGroups = $this->groupKeysForPrefix( $groups, 'vulnerabilities:' );
		$abandonedGroups = $this->groupKeysForPrefix( $groups, 'abandoned:' );

		$this->assertSame( 1, $this->groupCountForPrefix( $groups, 'plugins:' ) );
		$this->assertSame( 1, $this->groupCountForPrefix( $groups, 'themes:' ) );
		$this->assertCount( 1, $vulnerabilityGroups );
		$this->assertSame( 1, (int)( $groups[ $vulnerabilityGroups[ 0 ] ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $groups[ $vulnerabilityGroups[ 0 ] ][ 'status' ] ?? '' ) );
		$this->assertCount( 1, $abandonedGroups );
		$this->assertSame( 1, (int)( $groups[ $abandonedGroups[ 0 ] ][ 'count' ] ?? 0 ) );

		$vulnerablePayload = $this->processActionPayloadWithAdminBypass( VulnerabilitiesPane::SLUG, [ 'section' => 'vulnerable' ] );
		$this->assertRouteRenderOutputHealthy( $vulnerablePayload, 'notified vulnerability rail remains visible' );
		$vulnerableTab = \is_array( $vulnerablePayload[ 'render_data' ][ 'tab' ] ?? null )
			? $vulnerablePayload[ 'render_data' ][ 'tab' ]
			: [];
		$this->assertFalse( (bool)( $vulnerableTab[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 1, (int)( $vulnerableTab[ 'count_items' ] ?? 0 ) );
		$this->assertNotEmpty( $vulnerableTab[ 'items' ] ?? [] );
	}

	public function test_pro_deactivated_stale_scan_result_details_render_disabled_panes() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes', 'malware_php' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$displayOptions = new ScanResultsDisplayOptions();
		$this->assertDisabledDirectScanRenderPayload(
			$this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, $displayOptions->buildDisplayContextActionData() ),
			'pro-deactivated stale malware detail'
		);
		$this->assertDisabledDirectScanRenderPayload(
			$this->processActionPayloadWithAdminBypass(
				ActionsQueueAssetFileStatusDetail::SLUG,
				$displayOptions->buildSubjectActionData( 'plugin', $pluginSlug )
			),
			'pro-deactivated stale plugin subject detail'
		);
		$this->assertDisabledDirectScanRenderPayload(
			$this->processActionPayloadWithAdminBypass(
				ActionsQueueAssetFileStatusDetail::SLUG,
				$displayOptions->buildSubjectActionData( 'theme', $themeSlug )
			),
			'pro-deactivated stale theme subject detail'
		);
		$this->assertDisabledAssetCardsRenderPayload(
			$this->processActionPayloadWithAdminBypass( PluginsPane::SLUG, $displayOptions->buildDisplayContextActionData() ),
			'pro-deactivated stale plugin cards'
		);
		$this->assertDisabledAssetCardsRenderPayload(
			$this->processActionPayloadWithAdminBypass( ThemesPane::SLUG, $displayOptions->buildDisplayContextActionData() ),
			'pro-deactivated stale theme cards'
		);
		$this->assertDisabledRailPaneRenderPayload(
			$this->processActionPayloadWithAdminBypass( VulnerabilitiesPane::SLUG, [ 'section' => 'vulnerable' ] ),
			'pro-deactivated stale vulnerability rail'
		);

		$abandonedPayload = $this->processActionPayloadWithAdminBypass( VulnerabilitiesPane::SLUG, [ 'section' => 'abandoned' ] );
		$this->assertRouteRenderOutputHealthy( $abandonedPayload, 'pro-deactivated abandoned rail remains available' );
		$abandonedTab = \is_array( $abandonedPayload[ 'render_data' ][ 'tab' ] ?? null )
			? $abandonedPayload[ 'render_data' ][ 'tab' ]
			: [];
		$this->assertFalse( (bool)( $abandonedTab[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 1, (int)( $abandonedTab[ 'count_items' ] ?? 0 ) );
		$this->assertNotEmpty( $abandonedTab[ 'items' ] ?? [] );

		foreach ( [
			'plugins'         => 'asset_cards',
			'themes'          => 'asset_cards',
			'malware'         => 'direct_table',
			'vulnerabilities' => 'direct_table',
		] as $groupKey => $expectedShell ) {
			$groupsPayload = $this->loadSelectedGroupPayload( 'critical', $groupKey );
			$this->assertSame( $groupKey, (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
			$this->assertSame( $expectedShell, (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
			$this->assertSame( 'ajax_render', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'ex' ] ?? '' ) );
			$this->assertSame( [], $groupsPayload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? [] );

			$detailPayload = $this->renderSelectedGroupDetail( $groupsPayload );
			if ( $groupKey === 'plugins' || $groupKey === 'themes' ) {
				$this->assertDisabledAssetCardsRenderPayload( $detailPayload, 'selected disabled '.$groupKey.' group detail' );
			}
			elseif ( $groupKey === 'malware' ) {
				$this->assertDisabledDirectScanRenderPayload( $detailPayload, 'selected disabled malware group detail' );
			}
			else {
				$this->assertDisabledRailPaneRenderPayload( $detailPayload, 'selected disabled vulnerabilities group detail' );
			}
		}
	}

	public function test_actions_queue_groups_do_not_surface_disabled_review_sources_with_historical_results() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'N' )
			 ->optSet( 'enabled_scan_apc', 'N' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertSame( 0, $this->groupCountForPrefix( $groups, 'plugins:' ) );
		$this->assertSame( 0, $this->groupCountForPrefix( $groups, 'themes:' ) );
		$this->assertArrayNotHasKey( 'malware', $groups );
		$this->assertSame( 0, \count( \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, 'vulnerabilities:' )
				|| \str_starts_with( $key, 'abandoned:' )
		) ) );
	}

	public function test_disabled_historical_scan_results_do_not_surface_in_actions_queue_summary() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'N' )
			 ->optSet( 'file_scan_areas', [] )
			 ->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = $this->requireAtLeastInstalledThemes( 1 )[ 0 ];

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertAfsFileScanResult( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $scanId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing disabled historical scan results' );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$scans = $this->findZoneTile(
			\is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [],
			'scans'
		);

		$this->assertFalse( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertFalse( (bool)( $scans[ 'has_issues' ] ?? true ) );
		$this->assertSame( 0, (int)( $scans[ 'total_issues' ] ?? -1 ) );
	}

	public function test_actions_queue_groups_hide_file_locker_when_premium_unavailable() :void {
		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertArrayNotHasKey( 'file_locker', $groups );
	}

	public function test_actions_queue_groups_count_file_locker_when_enabled_and_problematic() :void {
		$this->prepareFileLockerRuntime();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$groups = $this->buildActionsQueueGroupMetrics();

		$this->assertSame( 1, (int)( $groups[ 'file_locker' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $groups[ 'file_locker' ][ 'status' ] ?? '' ) );
	}

	public function test_actions_queue_groups_dedupe_same_asset_across_vulnerable_and_abandoned_sections() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $pluginSlug,
			'is_abandoned' => 1,
		] );

		$groups = $this->buildActionsQueueGroupMetrics();
		$vulnerabilityGroups = \array_values( \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, 'vulnerabilities:' )
		) );
		$abandonedGroups = \array_values( \array_filter(
			\array_keys( $groups ),
			static fn( string $key ) :bool => \str_starts_with( $key, 'abandoned:' )
		) );

		$this->assertCount( 1, $vulnerabilityGroups );
		$this->assertCount( 1, $abandonedGroups );
		$this->assertSame( 'critical', (string)( $groups[ $vulnerabilityGroups[ 0 ] ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $groups[ $abandonedGroups[ 0 ] ][ 'status' ] ?? '' ) );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInstalledPlugins( int $minimum ) :array {
		$pluginFiles = $this->getInstalledPluginFiles();

		if ( \count( $pluginFiles ) < $minimum ) {
			$this->markTestSkipped( 'Not enough installed plugins are available for this integration fixture.' );
		}

		return \array_slice( $pluginFiles, 0, $minimum );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInstalledThemes( int $minimum ) :array {
		$stylesheets = Services::WpThemes()->getInstalledStylesheets();
		\natsort( $stylesheets );
		$stylesheets = \array_values( $stylesheets );

		if ( \count( $stylesheets ) < $minimum ) {
			$this->markTestSkipped( 'Not enough installed themes are available for this integration fixture.' );
		}

		return \array_slice( $stylesheets, 0, $minimum );
	}

	private function ensureInstalledThemeFixture() :void {
		$themeDir = \dirname( $this->themeFixtureStylePath() );
		$styleCss = $this->themeFixtureStylePath();
		$indexPhp = $themeDir.'/index.php';
		if ( !\is_dir( $themeDir ) ) {
			$this->assertTrue( \wp_mkdir_p( $themeDir ) );
			$this->tempDirs[] = $themeDir;
		}
		if ( \function_exists( 'register_theme_directory' ) ) {
			\register_theme_directory( \trailingslashit( WP_CONTENT_DIR ).'themes' );
		}
		if ( !\file_exists( $styleCss ) ) {
			$this->assertNotFalse( \file_put_contents( $styleCss, "/*\nTheme Name: Shield Integration Theme\nVersion: 1.0.0\n*/\n" ) );
			$this->tempPaths[] = $styleCss;
		}
		if ( !\file_exists( $indexPhp ) ) {
			$this->assertNotFalse( \file_put_contents( $indexPhp, "<?php\n" ) );
			$this->tempPaths[] = $indexPhp;
		}
		if ( \function_exists( 'wp_clean_themes_cache' ) ) {
			\wp_clean_themes_cache( true );
		}
	}

	private function themeFixtureStylePath() :string {
		return \trailingslashit( WP_CONTENT_DIR ).'themes/'.self::THEME_FIXTURE_STYLESHEET.'/style.css';
	}

	/**
	 * @param class-string<MaintenanceItemIgnore> $actionClass
	 */
	private function processMaintenanceAction( string $actionClass, array $data ) :array {
		$snapshot = $this->seedActionNonceContext( $actionClass );

		try {
			return ( new ActionProcessor() )->processAction( $actionClass::SLUG, $data )->payload();
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	/**
	 * @return list<string>
	 */
	private function getInstalledPluginFiles() :array {
		$pluginFiles = \array_values( \array_map(
			static fn( $file ) :string => (string)$file,
			\array_keys( Services::WpPlugins()->getPlugins() )
		) );
		\natsort( $pluginFiles );
		return \array_values( $pluginFiles );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInactivePlugins( int $minimum ) :array {
		$inactivePlugins = \array_values( \array_diff(
			$this->getInstalledPluginFiles(),
			Services::WpPlugins()->getActivePlugins()
		) );

		if ( \count( $inactivePlugins ) < $minimum ) {
			$this->markTestSkipped( 'Not enough inactive plugins are available for this integration fixture.' );
		}

		return \array_slice( $inactivePlugins, 0, $minimum );
	}

	private function insertUnfinishedScan( string $scanSlug, string $status = 'queued', int $readyAt = 0 ) :int {
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->status = $status;
		$record->ready_at = $readyAt;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
	}

	public function test_actions_queue_landing_uses_runtime_warning_as_root_focus_while_scans_are_in_flight() :void {
		$this->insertUnfinishedScan( 'afs', 'queued' );

		$payload = $this->renderActionsQueueLandingPage();
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$warning = ( new ScanResultsLagWarning() )->getText();

		$this->assertNotSame( '', $warning );
		$this->assertSame( $warning, (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? '' ) );
	}
}

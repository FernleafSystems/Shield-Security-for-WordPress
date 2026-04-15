<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueScanRailMetrics;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\InvestigationTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MaintenanceItemIgnore;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScanResultsDisplayFormSubmit;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\FileLocker as FileLockerPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Malware as MalwarePane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Plugins as PluginsPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Themes as ThemesPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Wordpress as WordpressPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	private array $optionsSnapshot = [];
	private array $tempPaths = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			MaintenanceIssueStateProvider::OPT_KEY,
			'file_locker',
			'filelocker_state',
			'scan_results_table_display',
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

	private function loadSelectedGroupPayload( string $bucket, string $groupKey ) :array {
		return $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => $bucket,
			'group'  => $groupKey,
		] );
	}

	private function renderSelectedGroupDetail( string $bucket, string $groupKey ) :array {
		$groupsPayload = $this->loadSelectedGroupPayload( $bucket, $groupKey );
		$detailRenderAction = $groupsPayload[ 'selected_group' ][ 'detail_render_action' ] ?? null;

		$this->assertIsArray( $detailRenderAction, 'Expected selected group to expose a direct detail render action array.' );
		$this->assertNotEmpty( $detailRenderAction, 'Expected selected group to expose a direct detail render action.' );

		return $this->processActionPayloadWithAdminBypass( AjaxRender::SLUG, $detailRenderAction );
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

	private function seedCriticalAssetAndVulnerabilityQueue() :void {
		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

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
		return TestDataFactory::pathFragmentFromAbsolutePath(
			WP_PLUGIN_DIR.'/'.( $pluginSlug ?? self::con()->base_file )
		);
	}

	private function themeMainPathFragment( ?string $themeSlug = null ) :string {
		$themeSlug = $themeSlug ?? \wp_get_theme()->get_stylesheet();
		return TestDataFactory::pathFragmentFromAbsolutePath( \get_theme_root().'/'.$themeSlug.'/style.css' );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function executeInvestigationTableFromHtml( string $html ) :array {
		$xpath = $this->createDomXPathFromHtml( $html );
		$table = $xpath->query( '//*[@data-investigation-table="1"]' )->item( 0 );
		$this->assertInstanceOf( \DOMElement::class, $table );

		$tableAction = \json_decode(
			\html_entity_decode( $table->getAttribute( 'data-table-action' ), \ENT_QUOTES ),
			true
		);
		$this->assertIsArray( $tableAction );

		$payload = $this->processActionPayloadWithAdminBypass( InvestigationTableAction::SLUG, \array_merge(
			$tableAction,
			[
				'sub_action'   => 'retrieve_table_data',
				'table_type'   => (string)$table->getAttribute( 'data-table-type' ),
				'subject_type' => (string)$table->getAttribute( 'data-subject-type' ),
				'subject_id'   => (string)$table->getAttribute( 'data-subject-id' ),
				'table_data'   => [
					'search'  => [ 'value' => '' ],
					'start'   => 0,
					'length'  => 10,
					'order'   => [],
					'columns' => [],
				],
			]
		) );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertIsArray( $payload[ 'datatable_data' ] ?? null );

		return $payload[ 'datatable_data' ];
	}

	/**
	 * @param array<string,mixed> $header
	 * @param array{type:string,file:string} $expectedScope
	 */
	private function assertIgnoreAllHeaderAction( array $header, array $expectedScope ) :void {
		$this->assertSame( 'Ignore All Results', (string)( $header[ 'actions' ][ 0 ][ 'label' ] ?? '' ) );
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

	private function assertDisplayOptionsHeader( array $header, array $expectedStates, bool $ignoredToggleDisabled = false ) :void {
		$displayOptions = (array)( $header[ 'display_options' ] ?? [] );
		$this->assertSame( 'Display Results', (string)( $displayOptions[ 'title' ] ?? '' ) );
		$this->assertCount( 3, $displayOptions[ 'controls' ] ?? [] );
		$actionData = \json_decode( (string)( $displayOptions[ 'action_json' ] ?? '' ), true );
		$this->assertIsArray( $actionData );
		$this->assertSame( ScanResultsDisplayFormSubmit::SLUG, (string)( $actionData[ 'ex' ] ?? '' ) );
		$this->assertSame(
			$expectedStates,
			\array_column( (array)( $displayOptions[ 'controls' ] ?? [] ), 'checked', 'name' )
		);
		$this->assertSame(
			[
				'include_ignored'  => $ignoredToggleDisabled,
				'include_repaired' => false,
				'include_deleted'  => false,
			],
			\array_column( (array)( $displayOptions[ 'controls' ] ?? [] ), 'disabled', 'name' )
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
		$this->assertDisplayOptionsHeader(
			(array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ),
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
			]
		);
		$this->assertSame( 'ajax_render', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'ex' ] ?? '' ) );

		$detailPayload = $this->renderSelectedGroupDetail( $bucket, $groupKey );
		$this->assertNotSame( '', (string)( $detailPayload[ 'html' ] ?? '' ) );
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
	 * @return array{count:int,status:string}
	 */
	private function getMaintenanceQueueMetricsFromLanding() :array {
		$payload = $this->renderActionsQueueLandingPage();
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$maintenance = $this->findZoneTile(
			\is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [],
			'maintenance'
		);

		return [
			'count'  => (int)( $maintenance[ 'total_issues' ] ?? 0 ),
			'status' => (string)( $maintenance[ 'status' ] ?? 'good' ),
		];
	}

	private function assertFlatEmptyStatePaneWithoutInvestigationTable( \DOMXPath $xpath, string $label ) :void {
		$this->assertXPathExists(
			$xpath,
			'//section[contains(concat(" ", normalize-space(@class), " "), " investigate-table-panel--flat ")]//*[contains(concat(" ", normalize-space(@class), " "), " alert-info ")]',
			$label.' should use the shared flat empty-state alert container'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigation-table="1"]',
			0,
			$label.' should not emit an investigation table contract'
		);
	}

	private function assertDisabledPaneWithoutInvestigationTable( \DOMXPath $xpath, string $label ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-scan-pane-disabled="1"]',
			$label.' should show the shared disabled callout'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			0,
			$label.' should not fall through to the standard empty state'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigation-table="1"]',
			0,
			$label.' should not emit an investigation table contract'
		);
	}

	private function assertInvestigationTableContractPresent(
		\DOMXPath $xpath,
		string $tableType,
		string $subjectType,
		string $subjectId,
		string $label
	) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and @data-table-type="'.$tableType.'" and @data-subject-type="'.$subjectType.'" and @data-subject-id="'.$subjectId.'"]',
			$label.' should use the shared investigation table contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and string-length(@data-datatables-init) > 0 and string-length(@data-table-action) > 0 and string-length(@data-scan-results-action) > 0 and string-length(@data-render-item-analysis) > 0]',
			$label.' should include the AJAX and action metadata required by the shared investigation table bootstrap'
		);
	}

	public function test_actions_queue_landing_renders_all_clear_with_drill_shell_when_queue_is_empty() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

		$this->assertModeShellPayload( $vars, 'actions', 'actions', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertSame( 'All Clear', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' ) );
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
		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="all-clear-context"]',
			'Empty actions queue should render the existing all-clear card'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1"]',
			1,
			'Empty actions queue should keep the drill-down shell when healthy bucket content exists'
		);
		$this->assertNotSame( '', \trim( $html ) );
	}

	public function test_actions_queue_landing_renders_drill_shell_and_bucket_cards_when_queue_has_items() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'actions', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '1 item', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' ) );
		$this->assertSame( '', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? '' ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertTrue( (bool)( $maintenance[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $maintenance[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 'maintenance', (string)( $maintenance[ 'panel_target' ] ?? '' ) );
		$this->assertNotEmpty( $maintenance[ 'assessment_rows' ] ?? [] );
		$this->assertSame( [ 'warning', 'good' ], \array_column( $maintenance[ 'maintenance_detail_groups' ] ?? [], 'status' ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $scans[ 'has_issues' ] ?? true ) );
		$this->assertNotEmpty( $scans[ 'assessment_rows' ] ?? [] );
		$maintenanceItemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$maintenanceItemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}
		$this->assertSame(
			DetailExpansionType::SIMPLE_TABLE,
			(string)( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'expansion' ][ 'type' ] ?? '' )
		);
		$this->assertNotEmpty( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertNotEmpty( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'toggle_action' ] ?? [] );
		$this->assertArrayNotHasKey( 'groups_render_action', $vars[ 'actions_queue_ajax' ] );
		$this->assertSame(
			ActionsQueueDrillDownGroups::SLUG,
			(string)( \json_decode( (string)( $vars[ 'actions_queue_ajax' ][ 'groups_render_action_json' ] ?? '' ), true )[ 'render_slug' ] ?? '' )
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-shell="1" and @data-drill-shell-mode="actions"]',
			'Actions queue should render the drill-down shell when the queue has items'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1" and string-length(@data-actions-queue-groups-action) > 0 and string-length(@data-actions-queue-detail-action) = 0]',
			'Actions queue should render only the groups AJAX action on the landing root'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="buckets" and string-length(@data-drill-layer-header) > 0]',
			'The shared drill shell should render PHP-prepared layer header JSON'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer-key="buckets"]//*[@data-drill-layer-compact-back="1"]',
			0,
			'The migrated drill shell should remove the old compact back control markup'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="drilldown"]/div[1][@data-drill-shell="1"]',
			'Actions queue should render the drill shell first for mobile-first stacking'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-target="groups" and @data-drill-bucket-selection]',
			2,
			'Bucket layer should render the two triage bucket cards'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-target="groups" and string-length(@data-drill-bucket-selection) > 0]',
			'Bucket layer should render PHP-prepared selection JSON for drill interactions'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-bucket-card__preview ")]',
			0,
			'Bucket layer should not render the removed bucket preview row'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-buckets ") and contains(concat(" ", normalize-space(@class), " "), " shield-stack ")]',
			'Bucket layer should keep the shared stack spacing container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-buckets ")]//*[contains(concat(" ", normalize-space(@class), " "), " item-box--good ")]',
			'Bucket layer should render the healthy summary content directly in the bucket layer'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" or @data-healthy-disclosure-body="1"]',
			0,
			'Bucket layer should not render the shared healthy disclosure wrapper'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-shield-rail-target]',
			0,
			'The migrated landing should not render the old scan-results rail sidebar'
		);
		$this->assertNotEmpty( \trim( $html ) );
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
		$this->assertNotSame( '', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'cta' ][ 'label' ] ?? '' ) );
		$this->assertSame(
			self::con()->plugin_urls->actionsQueueScans(),
			(string)( $renderData[ 'hrefs' ][ 'scan_results' ] ?? '' )
		);
		$this->assertSame( Services::WpGeneral()->getAdminUrl_Updates(), (string)( $renderData[ 'hrefs' ][ 'wp_updates' ] ?? '' ) );
	}

	public function test_maintenance_panel_inactive_plugin_rows_link_to_filtered_plugins_screen() :void {
		$pluginFile = $this->requireAtLeastInactivePlugins( 1 )[ 0 ];

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing inactive plugin links' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_inactive"]//a[@href="/wp-admin/plugins.php?s='.rawurlencode( $pluginFile ).'" and @data-bs-title="Manage this plugin" and contains(concat(" ", normalize-space(@class), " "), " actions-landing__table-icon-action ")]',
			'Inactive plugin maintenance rows should render the filtered plugins admin link as an icon action with tooltip'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@id="maintenance-expand-wp_plugins_inactive"]//a[contains(@href, "action=activate")]' )->length,
			'Inactive plugin maintenance rows should not offer activation links'
		);
	}

	public function test_groups_ajax_returns_bucket_groups_and_context() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Review next', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '1 item', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $payload[ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'active_sections', $payload );
		$this->assertArrayNotHasKey( 'healthy_sections', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertArrayNotHasKey( 'render_data', $payload );
		$this->assertArrayNotHasKey( 'render_output', $payload );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Review next', (string)( $payload[ 'bucket_selection' ][ 'label' ] ?? '' ) );
		$this->assertSame( 'Back to Actions Queue', (string)( $payload[ 'header' ][ 'active_back_label' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-groups="1"]',
			'Groups AJAX should render the selected bucket wrapper'
		);
		$this->assertXPathCount(
			$xpath,
			'//button[@data-drill-target="detail" and @data-drill-group-selection]',
			0,
			'Groups AJAX should not render a detail drill target for category cards'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row ")]',
			'Groups AJAX should render maintenance category rows inside the item-box'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box--warning ")]',
			'Groups AJAX should render active review maintenance cards with the warning item-box treatment'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row-icon ")]',
			'Groups AJAX should render the shared row icon element on populated maintenance rows'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row-inline-meta ")]',
			'Groups AJAX should render inline version meta on populated maintenance rows'
		);
		$this->assertNotEmpty( $activeGroup[ 'maintenance_rows' ] ?? [] );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row-summary ")]',
			0,
			'Groups AJAX should remove the grouped maintenance row context clutter'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " item-box__row-action ") and string-length(@data-actions-queue-maintenance-action) > 0]',
			'Groups AJAX should render the existing inline maintenance ignore action payload on category rows'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__header-link ")]',
			'Groups AJAX should render the maintenance management link in the category header'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " bi-arrow-right-circle ")]',
			0,
			'Groups AJAX should not render the legacy next-move arrow icon for category cards'
		);
	}

	public function test_groups_ajax_keeps_fully_ignored_review_group_visible_in_looking_good_section() :void {
		$this->setPluginUpdateAvailable();

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => self::con()->base_file,
		] );
		$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Review next', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '0 items', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ") and normalize-space()="Looking good"]',
			0,
			'Review groups AJAX should not render the removed healthy section heading'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-groups__healthy-stack ")]//*[contains(concat(" ", normalize-space(@class), " "), " item-box--good ")]',
			'Review groups AJAX should render healthy maintenance groups directly in the healthy stack'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-groups__healthy-stack ")]',
			'Review groups AJAX should keep the Actions Queue healthy stack spacing container'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" or @data-healthy-disclosure-body="1"]',
			0,
			'Review groups AJAX should not render the shared healthy disclosure wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " item-box__row-action ") and contains(@data-actions-queue-maintenance-action, "maintenance_item_unignore")]',
			'Review groups AJAX should keep the unignore action available on healthy ignored maintenance rows'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-landing__empty-state ")]',
			0,
			'Healthy review groups should render instead of the generic empty-state message'
		);
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
		$this->assertSame( '1 item', (string)( $payload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 1, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? 0 ) );
		$this->assertFalse( (bool)( $payload[ 'landing_refresh' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $payload[ 'landing_refresh' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'buckets_html' ] ?? '' ) );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Back to Review next', (string)( $payload[ 'selected_group' ][ 'header' ][ 'active_back_label' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'header' ][ 'display_options' ][ 'controls' ] ?? null );
	}

	public function test_groups_ajax_keeps_selected_fully_ignored_plugin_group_scoped_to_direct_table_detail() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;
		$plugin = Services::WpPlugins()->getPluginAsVo( $pluginSlug, true );
		$pluginTitle = $plugin === null ? '' : (string)$plugin->Title;
		$this->assertNotSame( '', $pluginTitle );

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		TestDataFactory::markScanResultItemIgnored( $tracked[ 'result_item_id' ] );
		$this->resetScanResultCountMemoization();

		$selectedGroupKey = 'plugins:'.$pluginSlug;
		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket'                  => 'critical',
			'group'                   => $selectedGroupKey,
			'include_landing_refresh' => 1,
		] );

		$this->assertSame( $selectedGroupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $payload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $payload[ 'selected_group' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertSame( $pluginTitle, (string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ) );
		$this->assertSame( $pluginTitle, (string)( $payload[ 'selected_group' ][ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '1 item', (string)( $payload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'Back to Fix now', (string)( $payload[ 'selected_group' ][ 'header' ][ 'active_back_label' ] ?? '' ) );
		$this->assertFalse( (bool)( $payload[ 'landing_refresh' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $payload[ 'landing_refresh' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertNotSame( '', (string)( $payload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertDisplayOptionsHeader(
			(array)( $payload[ 'selected_group' ][ 'header' ] ?? [] ),
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
			],
			true
		);
		$this->assertSame( 'actions_queue', (string)( $payload[ 'selected_group' ][ 'render_action_data' ][ 'display_context' ] ?? '' ) );
		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => true,
			],
			(array)( $payload[ 'selected_group' ][ 'render_action_data' ][ 'results_display_options' ] ?? [] )
		);
		$this->assertSame( 'actions_queue_asset_file_status_detail', (string)( $payload[ 'selected_group' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );

		$detailPayload = $this->renderSelectedGroupDetail( 'critical', $selectedGroupKey );
		$xpath = $this->createDomXPathFromHtml( (string)( $detailPayload[ 'html' ] ?? '' ) );

		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'plugin',
			$pluginSlug,
			'Ignored-only selected plugin detail AJAX'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			0,
			'Ignored-only selected plugin detail AJAX should keep the direct table instead of reopening the asset chooser'
		);
	}

	public function test_groups_ajax_keeps_active_vulnerabilities_separate_from_healthy_abandoned_assets_in_critical_bucket() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );
		TestDataFactory::insertCompletedScan( 'apc' );
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ") and normalize-space()="Known Vulnerabilities"]',
			'Active vulnerable findings should stay in the vulnerabilities group'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-groups__healthy-stack ")]//section[.//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ") and normalize-space()="Abandoned Assets"] and .//*[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card__title ") and normalize-space()="Abandoned Assets"]]',
			'Healthy abandoned assets should render under their own visible heading'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-groups__healthy-stack ")]//*[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card__title ") and normalize-space()="Vulnerabilities"]',
			0,
			'Healthy abandoned assets should no longer fall back to a healthy vulnerabilities card'
		);
	}

	public function test_groups_ajax_renders_shared_configure_cards_for_critical_bucket_groups() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Fix now', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '3 items', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'bucket_selection' ][ 'status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'active_sections', $payload );
		$this->assertArrayNotHasKey( 'healthy_sections', $payload );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ")]',
			3,
			'Critical groups AJAX should render one heading per finding group'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card ")]',
			3,
			'Critical groups AJAX should render shared Configure-style cards for all non-category groups'
		);
		$this->assertXPathCount(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card ") and @data-drill-target="detail" and @data-drill-group-selection]',
			2,
			'Only expandable shared cards should emit detail drill attributes'
		);
		$this->assertXPathCount(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card__footer-link ")]',
			2,
			'Linked vulnerability cards should render the native action links in the shared card footer'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card__footer-link ") and @href="/wp-admin/plugins.php" and not(@target)]',
			'Linked vulnerability cards should render the native plugin-management link in the shared card footer'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card__footer-link ") and @href="https://clk.shldscrty.com/shieldvulnerabilitylookup?type=plugin&amp;slug='.self::con()->base_file.'&amp;version='.self::con()->cfg->version().'" and @target="_blank" and @rel="noopener noreferrer"]',
			'Linked vulnerability cards should render the external lookup link with the expected attributes'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-group-card ")]',
			0,
			'Critical groups AJAX should not render the legacy group-card markup'
		);
	}

	public function test_groups_ajax_routes_abandoned_only_findings_to_fix_now() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;
		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $pluginSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );

		$this->assertSame( 'Fix now', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'bucket_selection' ][ 'status' ] ?? '' ) );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'html' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ") and normalize-space()="Abandoned Assets"]',
			'Abandoned-only findings should render under the abandoned assets heading'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " configure-zone-card status-critical ")]',
			'Abandoned-only findings should render as critical shared cards in the groups layer'
		);
	}

	public function test_detail_ajax_renders_selected_plugin_group_as_direct_investigation_table() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();
		$pluginSlug = self::con()->base_file;

		$groupsPayload = $this->loadSelectedGroupPayload( 'critical', 'plugins:'.$pluginSlug );
		$payload = $this->renderSelectedGroupDetail( 'critical', 'plugins:'.$pluginSlug );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( '1 item', (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'plugins:'.$pluginSlug, (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			(string)( $groupsPayload[ 'selected_group' ][ 'label' ] ?? '' ),
			(string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'title' ] ?? '' )
		);
		$this->assertNotSame( '', (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) );
		$this->assertSame( 'Ignore All Results', (string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'actions' ][ 0 ][ 'label' ] ?? '' ) );
		$this->assertSame( 'ignore_all', (string)( \json_decode(
			(string)( $groupsPayload[ 'selected_group' ][ 'header' ][ 'actions' ][ 0 ][ 'ajax_action_json' ] ?? '' ),
			true
		)[ 'sub_action' ] ?? '' ) );
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'plugin',
			$pluginSlug,
			'Detail AJAX plugin drill'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			0,
			'Detail AJAX should render the shared flat table directly instead of reopening the asset-card chooser'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-shield-rail-target]',
			0,
			'Detail AJAX should not re-render the removed rail sidebar'
		);
	}

	public function test_detail_ajax_selected_plugin_group_returns_populated_investigation_rows() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();
		$pluginSlug = self::con()->base_file;

		$payload = $this->renderSelectedGroupDetail( 'critical', 'plugins:'.$pluginSlug );

		$datatable = $this->executeInvestigationTableFromHtml( (string)( $payload[ 'html' ] ?? '' ) );

		$this->assertSame( 1, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertSame(
			[ $this->pluginMainPathFragment( $pluginSlug ) ],
			\array_column( $datatable[ 'data' ] ?? [], 'file' )
		);
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

		$themeSlug = \wp_get_theme()->get_stylesheet();
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

	public function test_plugin_detail_render_in_actions_queue_context_uses_asset_cards_shell() :void {
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
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			'Plugin pane render in Actions Queue context should use the asset-card shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-tiles="1" and contains(concat(" ", normalize-space(@class), " "), " actions-queue-asset-cards__grid ")]',
			'Plugin pane render in Actions Queue context should render the asset-card grid'
		);
	}

	public function test_ignored_plugin_group_keeps_forced_ignored_scope_and_stored_deleted_repaired_flags_on_direct_table_detail() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			 ->optSet( 'scan_results_table_display', [ 'include_repaired', 'include_deleted' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		foreach ( [ 1, 2 ] as $_ ) {
			$tracked = TestDataFactory::insertAfsFileScanResultTracked(
				$afsId,
				$this->pluginMainPathFragment( $pluginSlug ),
				[
					'is_in_plugin' => 1,
					'ptg_slug'     => $pluginSlug,
				]
			);
			TestDataFactory::markScanResultItemIgnored( (int)$tracked[ 'result_item_id' ] );
		}
		$this->resetScanResultCountMemoization();

		$groupsPayload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
			'group'  => 'plugins:'.$pluginSlug,
		] );
		$this->assertSame( 'plugins:'.$pluginSlug, (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertDisplayOptionsHeader(
			(array)( $groupsPayload[ 'selected_group' ][ 'header' ] ?? [] ),
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => true,
			],
			true
		);
		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => true,
				'ignored_only'     => true,
			],
			(array)( $groupsPayload[ 'selected_group' ][ 'render_action_data' ][ 'results_display_options' ] ?? [] )
		);
		$this->assertSame( 'actions_queue_asset_file_status_detail', (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );
		$detailPayload = $this->renderSelectedGroupDetail( 'critical', 'plugins:'.$pluginSlug );
		$this->assertNotSame( '', \trim( (string)( $detailPayload[ 'html' ] ?? '' ) ) );
		$datatable = $this->executeInvestigationTableFromHtml( (string)( $detailPayload[ 'html' ] ?? '' ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? -1 ) );
	}

	public function test_file_locker_detail_render_in_actions_queue_context_marks_lazy_asset_panels() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( FileLockerPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			'File Locker pane render in Actions Queue context should use the asset-card shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1" and @data-actions-queue-asset-panel-lazy="1" and @data-actions-queue-asset-panel-loaded="0" and string-length(@data-actions-queue-asset-render-action) > 0]',
			'File Locker pane render in Actions Queue context should expose the lazy render-action contract on its asset panel'
		);
	}

	public function test_healthy_file_locker_is_visible_on_landing_and_in_critical_healthy_stack() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->renderActionsQueueLandingPage();
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$queueRows = \is_array( $vars[ 'actions_queue_rows' ] ?? null ) ? $vars[ 'actions_queue_rows' ] : [];
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$fileLockerRows = \array_values( \array_filter(
			$queueRows,
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerRows );
		$this->assertSame( 'good', (string)( $fileLockerRows[ 0 ][ 'severity' ] ?? '' ) );
		$this->assertSame( 0, (int)( $fileLockerRows[ 0 ][ 'count' ] ?? -1 ) );

		$fileLockerAssessments = \array_values( \array_filter(
			\is_array( $scans[ 'assessment_rows' ] ?? null ) ? $scans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerAssessments );
		$this->assertSame( 'good', (string)( $fileLockerAssessments[ 0 ][ 'status' ] ?? '' ) );

		$groupsPayload = $this->loadSelectedGroupPayload( 'critical', 'file_locker' );
		$this->assertSame( 'file_locker', (string)( $groupsPayload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'asset_cards', (string)( $groupsPayload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertTrue( (bool)( $groupsPayload[ 'selected_group' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame( 0, (int)( $groupsPayload[ 'selected_group' ][ 'item_count' ] ?? -1 ) );
		$this->assertSame( 'actions_queue', (string)( $groupsPayload[ 'selected_group' ][ 'render_action_data' ][ 'display_context' ] ?? '' ) );
		$this->assertSame( FileLockerPane::SLUG, (string)( $groupsPayload[ 'selected_group' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );
	}

	public function test_file_locker_warning_clears_on_landing_immediately_after_reassessment() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();
		RuntimeTestState::primeShieldNetHandshake();

		$tempPath = \tempnam( \sys_get_temp_dir(), 'shield-file-locker-landing-' );
		$this->assertIsString( $tempPath );
		$this->tempPaths[] = $tempPath;
		$this->assertTrue( Services::WpFs()->putFileContent( $tempPath, 'original-file-content' ) );

		$handler = $this->requireDb( 'file_locker' );
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
		$warningQueueRows = \is_array( $warningVars[ 'actions_queue_rows' ] ?? null )
			? $warningVars[ 'actions_queue_rows' ]
			: [];
		$warningScans = $this->findZoneTile(
			\is_array( $warningVars[ 'zone_tiles' ] ?? null )
				? $warningVars[ 'zone_tiles' ]
				: [],
			'scans'
		);
		$fileLockerWarningRows = \array_values( \array_filter(
			$warningQueueRows,
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );
		$fileLockerWarningAssessments = \array_values( \array_filter(
			\is_array( $warningScans[ 'assessment_rows' ] ?? null ) ? $warningScans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerWarningRows );
		$this->assertSame( 'warning', (string)( $fileLockerWarningRows[ 0 ][ 'severity' ] ?? '' ) );
		$this->assertSame( 1, (int)( $fileLockerWarningRows[ 0 ][ 'count' ] ?? 0 ) );
		$this->assertCount( 1, $fileLockerWarningAssessments );
		$this->assertSame( 'warning', (string)( $fileLockerWarningAssessments[ 0 ][ 'status' ] ?? '' ) );

		self::con()->comps->file_locker->reassessLocksNow();

		$healthyPayload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $healthyPayload, 'actions queue landing file locker healthy state' );
		$healthyVars = \is_array( $healthyPayload[ 'render_data' ][ 'vars' ] ?? null )
			? $healthyPayload[ 'render_data' ][ 'vars' ]
			: [];
		$healthyQueueRows = \is_array( $healthyVars[ 'actions_queue_rows' ] ?? null )
			? $healthyVars[ 'actions_queue_rows' ]
			: [];
		$healthyScans = $this->findZoneTile(
			\is_array( $healthyVars[ 'zone_tiles' ] ?? null )
				? $healthyVars[ 'zone_tiles' ]
				: [],
			'scans'
		);
		$fileLockerHealthyRows = \array_values( \array_filter(
			$healthyQueueRows,
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );
		$fileLockerHealthyAssessments = \array_values( \array_filter(
			\is_array( $healthyScans[ 'assessment_rows' ] ?? null ) ? $healthyScans[ 'assessment_rows' ] : [],
			static fn( array $row ) :bool => (string)( $row[ 'key' ] ?? '' ) === 'file_locker'
		) );

		$this->assertCount( 1, $fileLockerHealthyRows );
		$this->assertSame( 'good', (string)( $fileLockerHealthyRows[ 0 ][ 'severity' ] ?? '' ) );
		$this->assertSame( 0, (int)( $fileLockerHealthyRows[ 0 ][ 'count' ] ?? -1 ) );
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


	public function test_wordpress_pane_render_uses_core_investigation_file_status_table_contract() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( WordpressPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'core',
			'core',
			'WordPress pane render'
		);
	}

	public function test_wordpress_pane_render_preserves_standard_no_results_message() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		TestDataFactory::insertCompletedScan( 'afs' );

		$payload = $this->processActionPayloadWithAdminBypass( WordpressPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertFlatEmptyStatePaneWithoutInvestigationTable( $xpath, 'WordPress pane render' );
	}

	public function test_plugin_pane_render_uses_investigation_file_status_table_contract() :void {
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
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target]',
			'Plugin pane render should keep the shared expandable summary row'
		);
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'plugin',
			$pluginSlug,
			'Plugin pane render'
		);
	}

	public function test_theme_pane_render_uses_investigation_file_status_table_contract() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'themes' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$themeSlug = \wp_get_theme()->get_stylesheet();
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( ThemesPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target]',
			'Theme pane render should keep the shared expandable summary row'
		);
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'theme',
			$themeSlug,
			'Theme pane render'
		);
	}

	public function test_plugin_pane_render_uses_disabled_callout_when_plugin_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertDisabledPaneWithoutInvestigationTable( $xpath, 'Plugin pane render' );
	}

	public function test_malware_pane_render_uses_disabled_callout_when_malware_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertDisabledPaneWithoutInvestigationTable( $xpath, 'Malware pane render' );
	}

	public function test_malware_pane_render_uses_shared_investigation_table_contract_when_enabled() :void {
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

		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'malware_scan_results',
			'malware',
			'malware',
			'Malware pane render'
		);
	}

	public function test_malware_pane_render_preserves_malware_empty_state_in_actions_queue() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();
		TestDataFactory::insertCompletedScan( 'afs' );

		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertFlatEmptyStatePaneWithoutInvestigationTable( $xpath, 'Malware pane render' );
	}

	public function test_scans_results_metrics_action_returns_exact_counts_for_enabled_tabs() :void {
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
		$themeSlug = \wp_get_theme()->get_stylesheet();

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

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'plugins' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'themes' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'abandoned' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'abandoned' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'malware' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $tabs[ 'file_locker' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'maintenance' ][ 'status' ] ?? '' ) );
		$this->assertSame( 6 + $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'rail_accent_status' ] ?? '' ) );
	}

	public function test_scans_results_metrics_action_returns_zero_neutral_entries_for_disabled_review_tabs_even_with_historical_results() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

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

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 0, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'plugins' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'themes' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'abandoned' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'abandoned' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'malware' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'maintenance' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $payload[ 'rail_accent_status' ] ?? '' ) );
	}

	public function test_disabled_historical_scan_results_do_not_surface_in_actions_queue_summary() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'N' )
			 ->optSet( 'file_scan_areas', [] )
			 ->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

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
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing disabled historical scan results' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1"]',
			1,
			'Historical results from disabled scans should still allow the healthy drill-down shell to render'
		);
	}

	public function test_scans_results_metrics_action_hides_file_locker_when_premium_unavailable() :void {
		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
	}

	public function test_scans_results_metrics_action_counts_file_locker_when_enabled_and_problematic() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $tabs[ 'file_locker' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ] + 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame(
			StatusPriority::highest( [ 'warning', $maintenance[ 'status' ] ], 'good' ),
			(string)( $tabs[ 'summary' ][ 'status' ] ?? '' )
		);
	}

	public function test_scans_results_metrics_action_dedupes_same_asset_across_vulnerable_and_abandoned_sections() :void {
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

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'abandoned' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'abandoned' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ] + 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame(
			StatusPriority::highest( [ 'critical', $maintenance[ 'status' ] ], 'good' ),
			(string)( $tabs[ 'summary' ][ 'status' ] ?? '' )
		);
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

	private function processMaintenanceAction( string $slug, array $data ) :array {
		return ( new ActionProcessor() )->processAction( $slug, $data )->payload();
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
}

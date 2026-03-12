<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\ActionsQueueScanRailMetrics,
	Actions\AjaxBatchRequests,
	Actions\Render\Components\Scans\Results\Malware as MalwarePane,
	Actions\Render\Components\Scans\Results\Plugins as PluginsPane,
	Actions\Render\Components\Scans\Results\Themes as ThemesPane,
	Actions\Render\PluginAdminPages\PageActionsQueueLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] );
		$this->resetScanResultCountMemoization();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
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

	private function findZoneTile( array $zoneTiles, string $key ) :array {
		$matches = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $key
		) );
		$this->assertCount( 1, $matches, 'Expected exactly one zone tile for '.$key );
		return $matches[ 0 ] ?? [];
	}

	private function insertFileLockRecord( string $type, string $path, int $detectedAt = 0 ) :void {
		$handler = $this->requireDb( 'file_locker' );
		$record = $handler->getRecord();
		$record->type = $type;
		$record->path = $path;
		$record->hash_original = \sha1( $type.'-original' );
		$record->hash_current = \sha1( $type.'-current' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-'.$type;
		$record->detected_at = $detectedAt;
		$handler->getQueryInserter()->insert( $record );
	}

	public function test_actions_queue_landing_keeps_zone_tiles_interactive_without_scan_findings() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertIsString( $strip[ 'subtext' ] ?? null );
		$this->assertCount( 2, $zoneTiles );
		$this->assertCount(
			2,
			\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
		);
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'scans' )[ 'assessment_rows' ] ?? [] );
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'maintenance' )[ 'assessment_rows' ] ?? [] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertSame( [], $vars[ 'scans_results' ] ?? [] );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-actions-landing="1"][@data-actions-queue-metrics-action]',
			0,
			'Actions queue all-clear page should not expose a metrics action payload'
		);
	}

	public function test_maintenance_items_render_tiles_and_maintenance_panel() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '', (string)( $strip[ 'subtext' ] ?? '' ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertTrue( (bool)( $maintenance[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $maintenance[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 'maintenance', (string)( $maintenance[ 'panel_target' ] ?? '' ) );
		$this->assertNotEmpty( $maintenance[ 'assessment_rows' ] ?? [] );
		$this->assertSame( [ 'warning', 'good' ], \array_column( $maintenance[ 'maintenance_detail_groups' ] ?? [], 'status' ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $scans[ 'has_issues' ] ?? true ) );
		$this->assertNotEmpty( $scans[ 'assessment_rows' ] ?? [] );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip" and contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip ")]',
			'Actions queue populated shared strip root marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[@role="progressbar"]',
			0,
			'Actions queue populated strip should not render a progressbar'
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
		$this->assertNotSame( '', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'cta' ][ 'label' ] ?? '' ) );
		$this->assertSame(
			self::con()->plugin_urls->actionsQueueScans(),
			(string)( $renderData[ 'hrefs' ][ 'scan_results' ] ?? '' )
		);
		$this->assertSame( Services::WpGeneral()->getAdminUrl_Updates(), (string)( $renderData[ 'hrefs' ][ 'wp_updates' ] ?? '' ) );
	}

	public function test_scan_result_items_enable_scans_zone_and_embedded_results_payload() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing scans state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$scansResults = \is_array( $vars[ 'scans_results' ] ?? null ) ? $vars[ 'scans_results' ] : [];
		$railTabs = \array_column( \is_array( $scansResults[ 'vars' ][ 'rail_tabs' ] ?? null ) ? $scansResults[ 'vars' ][ 'rail_tabs' ] : [], 'key' );
		$tabsByKey = [];
		foreach ( \is_array( $scansResults[ 'vars' ][ 'rail_tabs' ] ?? null ) ? $scansResults[ 'vars' ][ 'rail_tabs' ] : [] as $tab ) {
			$tabsByKey[ (string)( $tab[ 'key' ] ?? '' ) ] = $tab;
		}
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertSame( 'scans', (string)( $scans[ 'panel_target' ] ?? '' ) );
		$this->assertGreaterThan( 0, (int)( $scans[ 'total_issues' ] ?? 0 ) );
		$this->assertNotEmpty( $scansResults );
		$this->assertContains( 'summary', $railTabs );
		$this->assertContains( 'wordpress', $railTabs );
		$this->assertContains( 'plugins', $railTabs );
		$this->assertContains( 'themes', $railTabs );
		$this->assertContains( 'vulnerabilities', $railTabs );
		$this->assertContains( 'malware', $railTabs );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-scope="1"]',
			'Actions queue scans shell should render the scoped rail layout'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-target="summary" and @data-bs-toggle="tab" and @role="tab"]',
			'Actions queue scans shell should render Bootstrap tab triggers in the rail'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-scope="1"]//*[contains(concat(" ", normalize-space(@class), " "), " tab-content ")]/*[@data-shield-rail-pane="summary"]',
			'Actions queue scans shell should render the scan panes inside a Bootstrap tab-content container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="wordpress" and @data-actions-queue-pane-loaded="0" and string-length(@data-actions-queue-render-action) > 0]',
			'Actions queue scans shell should expose lazy-load metadata for heavy panes'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="malware" and @data-actions-queue-pane-loaded="0" and string-length(@data-actions-queue-render-action) > 0]',
			'Actions queue scans shell should expose lazy-load metadata for disabled review tabs too'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="wordpress"]//*[@data-actions-queue-pane-placeholder="1"]',
			'Actions queue scans shell should render loading placeholders for lazy panes'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="wordpress"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			0,
			'Actions queue scans shell should not render empty-state copy for lazy panes before AJAX hydration'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="summary" and @data-actions-queue-pane-loaded="1"]',
			'Actions queue scans shell should keep summary pane eager'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1" and string-length(@data-actions-queue-metrics-action) > 0]',
			'Actions queue scans shell should expose the background metrics action'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1" and string-length(@data-actions-queue-preload-action) > 0]',
			'Actions queue scans shell should expose the background preload action'
		);
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertNull( $tabsByKey[ 'plugins' ][ 'count' ] ?? -1 );
		$this->assertNull( $tabsByKey[ 'themes' ][ 'count' ] ?? -1 );
		$this->assertNull( $tabsByKey[ 'vulnerabilities' ][ 'count' ] ?? -1 );
		$this->assertNull( $tabsByKey[ 'malware' ][ 'count' ] ?? -1 );
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

	public function test_scans_results_shell_starts_enabled_tabs_lazy_without_eager_badges() :void {
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
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'       => $themeSlug,
			'is_abandoned'  => 1,
		] );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing shared scan results tabs' );
		$scansResults = \is_array( $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ] ?? null )
			? $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ]
			: [];
		$xpath = $this->createDomXPathFromHtml( $html );
		$railTabs = \is_array( $scansResults[ 'vars' ][ 'rail_tabs' ] ?? null )
			? \array_values( $scansResults[ 'vars' ][ 'rail_tabs' ] )
			: [];
		$tabsByKey = [];
		foreach ( $railTabs as $tab ) {
			$tabsByKey[ (string)( $tab[ 'key' ] ?? '' ) ] = $tab;
		}

		$this->assertContains( 'plugins', \array_keys( $tabsByKey ) );
		$this->assertContains( 'themes', \array_keys( $tabsByKey ) );
		$this->assertContains( 'vulnerabilities', \array_keys( $tabsByKey ) );
		$this->assertContains( 'malware', \array_keys( $tabsByKey ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-target="malware" and @data-bs-toggle="tab" and @role="tab"]',
			'Actions queue scans shell should render the malware rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1"]//*[@data-shield-rail-pane="malware" and @data-actions-queue-pane-loaded="0" and string-length(@data-actions-queue-render-action) > 0]',
			'Actions queue scans shell should expose malware lazy-load metadata'
		);
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'plugins' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'themes' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'vulnerabilities' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'malware' ] );
		$this->assertNull( $tabsByKey[ 'plugins' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'themes' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'vulnerabilities' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'malware' ][ 'count' ] );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'neutral', (string)( $tabsByKey[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertFalse( (bool)( $tabsByKey[ 'plugins' ][ 'is_loaded' ] ?? true ) );
		$this->assertFalse( (bool)( $tabsByKey[ 'themes' ][ 'is_loaded' ] ?? true ) );
		$this->assertFalse( (bool)( $tabsByKey[ 'vulnerabilities' ][ 'is_loaded' ] ?? true ) );
		$this->assertFalse( (bool)( $tabsByKey[ 'malware' ][ 'is_loaded' ] ?? true ) );
		$this->assertSame(
			ActionsQueueScanRailMetrics::SLUG,
			(string)( $scansResults[ 'vars' ][ 'metrics_action' ][ 'ex' ] ?? '' )
		);
		$this->assertSame(
			AjaxBatchRequests::SLUG,
			(string)( $scansResults[ 'vars' ][ 'preload_action' ][ 'ex' ] ?? '' )
		);
		$this->assertTrue( (bool)( $tabsByKey[ 'plugins' ][ 'show_count_placeholder' ] ?? false ) );
		$this->assertTrue( (bool)( $tabsByKey[ 'themes' ][ 'show_count_placeholder' ] ?? false ) );
		$this->assertTrue( (bool)( $tabsByKey[ 'vulnerabilities' ][ 'show_count_placeholder' ] ?? false ) );
		$this->assertTrue( (bool)( $tabsByKey[ 'malware' ][ 'show_count_placeholder' ] ?? false ) );
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
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " shield-detail-item ")][1]//*[@data-investigation-table="1" and @data-table-type="file_scan_results" and @data-subject-type="plugin" and @data-subject-id="'.$pluginSlug.'"]',
			'Plugin pane render should use the shared investigation file status table contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and string-length(@data-datatables-init) > 0 and string-length(@data-table-action) > 0 and string-length(@data-scan-results-action) > 0 and string-length(@data-render-item-analysis) > 0]',
			'Plugin pane render should include the AJAX and action metadata required by the shared investigation table bootstrap'
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
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " shield-detail-item ")][1]//*[@data-investigation-table="1" and @data-table-type="file_scan_results" and @data-subject-type="theme" and @data-subject-id="'.$themeSlug.'"]',
			'Theme pane render should use the shared investigation file status table contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and string-length(@data-datatables-init) > 0 and string-length(@data-table-action) > 0 and string-length(@data-scan-results-action) > 0 and string-length(@data-render-item-analysis) > 0]',
			'Theme pane render should include the AJAX and action metadata required by the shared investigation table bootstrap'
		);
	}

	public function test_plugin_pane_render_uses_disabled_callout_when_plugin_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-scan-pane-disabled="1"]',
			'Plugin pane render should show the shared disabled callout when plugin scanning is unavailable'
		);
		$this->assertStringContainsString(
			\sprintf(
				__( 'Scanning Plugin & Theme Files is available only with the Pro version of %s.', 'wp-simple-firewall' ),
				self::con()->labels->Name
			),
			$html
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			0,
			'Plugin pane render should not fall through to the standard empty state when disabled'
		);
	}

	public function test_malware_pane_render_uses_disabled_callout_when_malware_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-scan-pane-disabled="1"]',
			'Malware pane render should show the shared disabled callout when malware scanning is unavailable'
		);
		$this->assertStringContainsString(
			__( 'Malware Scanning is not enabled.', 'wp-simple-firewall' ),
			$html
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			0,
			'Malware pane render should not fall through to the standard empty state when disabled'
		);
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
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file-2.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
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

		$this->assertSame( 1, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'plugins' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'themes' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'malware' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 6, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
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
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
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

		$this->assertSame( 0, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'plugins' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'themes' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'malware' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( 0, (int)( $tabs[ 'summary' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $payload[ 'rail_accent_status' ] ?? '' ) );
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
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing disabled historical scan results' );

		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertSame( [], $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ] ?? [] );
	}

	public function test_scans_results_metrics_action_hides_file_locker_when_premium_unavailable() :void {
		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];

		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( 0, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
	}

	public function test_scans_results_metrics_action_counts_file_locker_when_enabled_and_problematic() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];

		$this->assertSame( 1, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $tabs[ 'file_locker' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
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

		$this->assertSame( 1, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
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
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] );
		$this->resetScanResultCountMemoization();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
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

	public function test_actions_queue_landing_keeps_zone_tiles_interactive_without_scan_findings() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

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
		$tabs = \array_column( \is_array( $scansResults[ 'vars' ][ 'tabs' ] ?? null ) ? $scansResults[ 'vars' ][ 'tabs' ] : [], 'key' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertSame( 'scans', (string)( $scans[ 'panel_target' ] ?? '' ) );
		$this->assertGreaterThan( 0, (int)( $scans[ 'total_issues' ] ?? 0 ) );
		$this->assertNotEmpty( $scansResults );
		$this->assertContains( 'summary', $tabs );
		$this->assertContains( 'wordpress', $tabs );
		$this->assertNotContains( 'vulnerabilities', $tabs );
		$this->assertXPathExists(
			$xpath,
			'//*[@id="ScanResultsTabs"]//*[@data-shield-rail-scope="1"]',
			'Actions queue scans shell should render the scoped rail layout'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@id="ScanResultsTabsNav"]',
			0,
			'Actions queue scans shell should not render the legacy bootstrap tab nav'
		);
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

	public function test_scans_results_shell_shows_plugin_theme_and_vulnerability_tabs_only_when_findings_exist() :void {
		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

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
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing shared scan results tabs' );
		$scansResults = \is_array( $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ] ?? null )
			? $payload[ 'render_data' ][ 'vars' ][ 'scans_results' ]
			: [];
		$tabs = \array_column( \is_array( $scansResults[ 'vars' ][ 'tabs' ] ?? null ) ? $scansResults[ 'vars' ][ 'tabs' ] : [], 'key' );
		$vulnerabilitySections = \is_array( $scansResults[ 'vars' ][ 'vulnerabilities' ][ 'sections' ] ?? null )
			? $scansResults[ 'vars' ][ 'vulnerabilities' ][ 'sections' ]
			: [];

		$this->assertContains( 'plugins', $tabs );
		$this->assertContains( 'themes', $tabs );
		$this->assertContains( 'vulnerabilities', $tabs );
		$this->assertNotEmpty( $vulnerabilitySections[ 'vulnerable' ][ 'items' ] ?? [] );
		$this->assertNotEmpty( $vulnerabilitySections[ 'abandoned' ][ 'items' ] ?? [] );
	}
}

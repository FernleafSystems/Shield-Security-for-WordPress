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

	private function findZoneTile( array $zoneTiles, string $key ) :array {
		$matches = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $key
		) );
		$this->assertCount( 1, $matches, 'Expected exactly one zone tile for '.$key );
		return $matches[ 0 ] ?? [];
	}

	public function test_all_clear_state_exposes_payload_contract_without_enabled_tiles() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing all-clear' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$allClear = \is_array( $vars[ 'all_clear' ] ?? null ) ? $vars[ 'all_clear' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertSame( 'good', (string)( $strip[ 'severity' ] ?? '' ) );
		$this->assertIsString( $strip[ 'subtext' ] ?? null );
		$this->assertSame( 0, (int)( $strip[ 'total_items' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $strip[ 'critical_count' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $strip[ 'warning_count' ] ?? -1 ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertCount(
			0,
			\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
		);
		$this->assertNotSame( '', (string)( $allClear[ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $allClear[ 'subtitle' ] ?? '' ) );
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $allClear[ 'zone_chips' ] ?? [], 'slug' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip" and contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip ")]',
			'Actions queue all-clear shared strip root marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip__chip ")]',
			'Actions queue all-clear shared strip chip marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[@role="progressbar"]',
			0,
			'Actions queue all-clear strip should not render a progressbar'
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
		$this->assertFalse( (bool)( $scans[ 'is_enabled' ] ?? true ) );
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

		$this->assertArrayNotHasKey( 'cta', $itemsByKey[ 'wp_plugins_updates' ] ?? [] );
		$this->assertSame(
			self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			(string)( $renderData[ 'hrefs' ][ 'scan_results' ] ?? '' )
		);
		$this->assertSame( Services::WpGeneral()->getAdminUrl_Updates(), (string)( $renderData[ 'hrefs' ][ 'wp_updates' ] ?? '' ) );
	}

	public function test_scan_result_items_enable_scans_zone_and_embedded_results_payload() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing scans state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$scansResults = \is_array( $vars[ 'scans_results' ] ?? null ) ? $vars[ 'scans_results' ] : [];
		$vulnerabilities = \is_array( $vars[ 'scans_vulnerabilities' ] ?? null ) ? $vars[ 'scans_vulnerabilities' ] : [];

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertSame( 'scans', (string)( $scans[ 'panel_target' ] ?? '' ) );
		$this->assertGreaterThan( 0, (int)( $scans[ 'total_issues' ] ?? 0 ) );
		$this->assertNotEmpty( $scansResults );
		$this->assertSame( 0, (int)( $vulnerabilities[ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $vulnerabilities[ 'status' ] ?? '' ) );
		$this->assertIsArray( $vulnerabilities[ 'items' ] ?? null );
	}
}

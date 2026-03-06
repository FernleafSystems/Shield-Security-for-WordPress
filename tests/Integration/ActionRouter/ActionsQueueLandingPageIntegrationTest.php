<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	BuiltMetersFixture,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;
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
		$this->resetBuiltMetersCache();
		$this->setOverallConfigMeterComponents( [] );
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		parent::tear_down();
	}

	private function renderActionsQueueLandingPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);
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
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing all-clear' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$allClear = \is_array( $vars[ 'all_clear' ] ?? null ) ? $vars[ 'all_clear' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertSame( 'good', (string)( $strip[ 'severity' ] ?? '' ) );
		$this->assertSame( 'All Clear', (string)( $strip[ 'label' ] ?? '' ) );
		$this->assertSame( 'No actions currently require your attention.', (string)( $strip[ 'summary_text' ] ?? '' ) );
		$this->assertIsString( $strip[ 'subtext' ] ?? null );
		$this->assertCount( 2, $zoneTiles );
		$this->assertCount(
			0,
			\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
		);
		$this->assertNotSame( '', (string)( $allClear[ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $allClear[ 'subtitle' ] ?? '' ) );
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $allClear[ 'zone_chips' ] ?? [], 'slug' ) );
	}

	public function test_maintenance_items_render_tiles_and_maintenance_panel() :void {
		$this->setOverallConfigMeterComponents( [
			[
				'slug'              => 'wp_updates',
				'is_protected'      => false,
				'title'             => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'  => 'There is an upgrade available for WordPress.',
				'href_full'         => self::con()->plugin_urls->adminHome(),
				'fix'               => 'Fix',
			],
		] );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '', (string)( $strip[ 'subtext' ] ?? '' ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertTrue( (bool)( $maintenance[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $maintenance[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 'maintenance', (string)( $maintenance[ 'panel_target' ] ?? '' ) );
		$this->assertFalse( (bool)( $scans[ 'is_enabled' ] ?? true ) );
	}

	public function test_maintenance_panel_exposes_inactive_asset_ctas_and_updates_href() :void {
		$this->setOverallConfigMeterComponents( [
			[
				'slug'              => 'wp_plugins_inactive',
				'is_protected'      => false,
				'title'             => 'Inactive Plugins',
				'title_unprotected' => 'Inactive Plugins',
				'desc_unprotected'  => 'There are plugins installed that are not active.',
				'href_full'         => '/wp-admin/plugins.php',
				'fix'               => 'Fix',
			],
			[
				'slug'              => 'wp_themes_inactive',
				'is_protected'      => false,
				'title'             => 'Inactive Themes',
				'title_unprotected' => 'Inactive Themes',
				'desc_unprotected'  => 'There are themes installed that are not active.',
				'href_full'         => '/wp-admin/themes.php',
				'fix'               => 'Fix',
			],
			[
				'slug'              => 'wp_updates',
				'is_protected'      => false,
				'title'             => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'  => 'There is an upgrade available for WordPress.',
				'href_full'         => '/wp-admin/update-core.php',
				'fix'               => 'Fix',
			],
		] );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance ctas' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$maintenance = $this->findZoneTile( \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [], 'maintenance' );
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertSame( '/wp-admin/plugins.php', (string)( $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'href' ] ?? '' ) );
		$this->assertSame( 'Go to plugins', (string)( $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'label' ] ?? '' ) );
		$this->assertSame( '/wp-admin/themes.php', (string)( $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'href' ] ?? '' ) );
		$this->assertSame( 'Go to themes', (string)( $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'label' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'cta', $itemsByKey[ 'wp_updates' ] ?? [] );
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

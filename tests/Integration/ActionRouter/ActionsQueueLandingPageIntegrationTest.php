<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	BuiltMetersFixture,
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;
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

	public function test_all_clear_state_renders_severity_strip_and_all_clear_card_without_tiles() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$strip = $payload[ 'render_data' ][ 'vars' ][ 'severity_strip' ] ?? [];
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing all-clear' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', (string)( $strip[ 'subtext' ] ?? '' ) );
		$this->assertModeShellAndAccentContract( $xpath, 'actions', 'critical', 'Actions', true );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]',
			'Severity strip marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[contains(@class,"actions-landing__severity-chip")]',
			'Severity strip chip marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[contains(@class,"actions-landing__severity-label") and normalize-space()="Queue Status"]',
			'Severity strip queue-status label'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="severity-strip"]//*[contains(@class,"actions-landing__severity-summary")]',
			'Severity strip summary marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="all-clear-context"]',
			'All-clear context marker'
		);
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-section="tiles"]', 0, 'Tiles section hidden for all-clear state' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1"]', 0, 'Mode tiles hidden for all-clear state' );
		$this->assertXPathCount( $xpath, '//*[@data-mode-panel="1"]', 0, 'Mode panels hidden for all-clear state' );
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
		$strip = $payload[ 'render_data' ][ 'vars' ][ 'severity_strip' ] ?? [];
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( '', (string)( $strip[ 'subtext' ] ?? '' ) );
		$this->assertModeShellAndAccentContract( $xpath, 'actions', 'critical', 'Actions', true );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="tiles"]',
			'Tiles section marker'
		);
		$this->assertXPathCount( $xpath, '//*[@data-mode-tile="1"]', 2, 'Two-zone tile contract marker' );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-zone="maintenance" and @data-mode-tile-disabled="0"]',
			'Maintenance tile enabled marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1" and @data-actions-panel="maintenance"]',
			'Maintenance panel marker'
		);
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-section="all-clear-context"]', 0, 'All-clear context hidden when queue has items' );
	}

	public function test_maintenance_panel_renders_single_footer_action_and_inactive_asset_ctas() :void {
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
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance ctas' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-panel="maintenance"]//*[contains(@class,"actions-landing__summary-action") and @href="/wp-admin/plugins.php"]',
			'Inactive plugins CTA marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-panel="maintenance"]//*[contains(@class,"actions-landing__summary-action") and @href="/wp-admin/themes.php"]',
			'Inactive themes CTA marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-panel="maintenance"]//a[@href="/wp-admin/update-core.php" and contains(@class,"btn")]',
			'Maintenance footer WordPress updates action'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-actions-panel="maintenance"]//a[@href="/wp-admin/plugins.php" and contains(@class,"btn") and not(contains(@class,"actions-landing__summary-action"))]',
			0,
			'Maintenance footer no longer exposes Open Plugins'
		);
	}

	public function test_scan_result_items_render_scans_panel_tabs_and_embedded_results_shell() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $scanId, 'is_in_core' );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing scans state' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellAndAccentContract( $xpath, 'actions', 'critical', 'Actions', true );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-zone="scans" and @data-mode-tile-disabled="0"]',
			'Scans tile enabled marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1" and @data-actions-panel="scans"]',
			'Scans panel marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="ActionsQueueScansTabsNav"]',
			'Scans panel tabs marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="actions-queue-scans-vulnerabilities-tab" and @aria-controls="actions-queue-scans-vulnerabilities"]',
			'Scans vulnerabilities tab marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="actions-queue-scans-summary"]//button[@data-bs-target="#actions-queue-scans-results"]',
			'Summary footer tab-open button marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@id="actions-queue-scans-summary"]//a[@href="/admin/scans/results"]',
			0,
			'Summary footer no longer links to full scan results page'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="ScanResultsTabs"]',
			'Embedded scan results shell marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="h-tabs-file_locker-tab" and @aria-controls="h-tabs-file_locker"]',
			'Embedded scan results file locker tab contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="h-tabs-file_locker" and @aria-labelledby="h-tabs-file_locker-tab"]',
			'Embedded scan results file locker panel contract'
		);
		$this->assertXPathCount( $xpath, '//*[@data-actions-queue-section="all-clear-context"]', 0, 'All-clear context hidden when scan issues exist' );
	}
}

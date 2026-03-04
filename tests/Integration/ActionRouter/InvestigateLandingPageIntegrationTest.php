<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderInvestigateLandingPage( array $extra = [] ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
			$extra
		);
	}

	public function test_landing_renders_final_subject_tiles_and_disabled_premium_integrations_marker() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertNotSame( '', $html, 'Expected non-empty render output for investigate landing.' );
		$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'Investigate landing render exception check' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-section="selector"]',
			'Landing selector section marker'
		);
		$this->assertModeShellAndAccentContract( $xpath, 'investigate', 'info', 'Investigate', true );
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]//form',
			0,
			'Landing selector should not render lookup forms'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]/h5',
			0,
			'Landing selector should not render in-body heading'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]/div[contains(concat(" ", normalize-space(@class), " "), " investigate-landing__section-label ")]',
			0,
			'Landing selector should not render section-label wrapper'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="lookup-shell"]',
			0,
			'Landing lookup shell marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-landing-hint="1" and not(contains(concat(" ", normalize-space(@class), " "), " d-none ")) and @aria-hidden="false"]',
			'Investigate landing hint is visible without active subject'
		);

		foreach ( [ 'user', 'ip', 'plugin', 'theme', 'core', 'live_traffic' ] as $subjectKey ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-investigate-subject="'.$subjectKey.'"]',
				'Landing '.$subjectKey.' subject marker'
			);
		}

		$this->assertXPathExists(
			$xpath,
			'//div[@data-investigate-subject="premium_integrations" and @aria-disabled="true"]',
			'Landing premium integrations disabled marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-tile="1"]',
			7,
			'Investigate shared mode tile marker count'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]',
			6,
			'Investigate inline panel marker count'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1" and @data-investigate-batch-action]',
			'Investigate landing batch action marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1" and @data-investigate-panel-error]',
			'Investigate landing panel error marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-investigate-panel-loaded="0"]',
			6,
			'Investigate landing preloads only active panel marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-investigate-panel-live="1"]',
			1,
			'Investigate landing live panel marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]//*[@data-investigate-panel-tabs="1"]',
			6,
			'Investigate landing panel tabs host marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigate-panel and @data-mode-panel="1"]//*[@data-investigate-panel-content="1"]',
			6,
			'Investigate landing panel content host marker'
		);
		$this->assertSharedModePanelMarkerCount( $xpath, 6, 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-render-action', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-loaded', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-live', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-target-default', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-static-target', 'Investigate' );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1" and (contains(concat(" ", normalize-space(@class), " "), " status-good ") or contains(concat(" ", normalize-space(@class), " "), " status-warning ") or contains(concat(" ", normalize-space(@class), " "), " status-critical ") or contains(concat(" ", normalize-space(@class), " "), " status-info ") or contains(concat(" ", normalize-space(@class), " "), " status-neutral "))]',
			6,
			'Investigate mode panels include status class on panel root'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]//button[@data-mode-panel-close="1" and contains(concat(" ", normalize-space(@class), " "), " mode-panel-close-btn ")]',
			6,
			'Investigate mode panel close button uses minimal close class'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]//button[contains(concat(" ", normalize-space(@class), " "), " btn-outline-secondary ")]',
			0,
			'Investigate mode panel close button no longer uses bootstrap outline class'
		);
	}

	public function test_lookup_preload_hides_landing_hint_and_renders_subject_header_contract() :void {
		$payload = $this->renderInvestigateLandingPage( [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.88',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertNotSame( '', $html, 'Expected non-empty render output for investigate landing preload.' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-landing-hint="1" and contains(concat(" ", normalize-space(@class), " "), " d-none ") and @aria-hidden="true"]',
			'Investigate landing hint is hidden when a panel is pre-opened'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip" and @aria-hidden="false"]',
			'Investigate IP panel opens for preloaded lookup'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-subject-header ")]',
			'Investigate IP panel subject header is visible for preloaded lookup'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[contains(concat(" ", normalize-space(@class), " "), " investigate-subject-header__title ") and normalize-space()="203.0.113.88"]',
			'Investigate IP panel subject header title matches lookup value'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="ip"]//*[@data-investigate-change-subject="1"]',
			'Investigate IP panel exposes change subject action'
		);
	}
}

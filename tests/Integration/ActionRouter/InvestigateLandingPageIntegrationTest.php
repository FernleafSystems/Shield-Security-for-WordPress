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

	private function renderInvestigateLandingPage() :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
		);
	}

	public function test_landing_renders_final_subject_tiles_and_disabled_premium_integrations_marker() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
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
		$this->assertSharedModePanelMarkerCount( $xpath, 6, 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-render-action', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-loaded', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'investigate-panel-live', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-target-default', 'Investigate' );
		$this->assertModePanelHasDataAttribute( $xpath, 'mode-panel-static-target', 'Investigate' );
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions, PluginAdminRouteRenderAssertions;

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

	public function test_landing_renders_subject_tiles_and_disabled_woocommerce_marker() :void {
		$payload = $this->renderInvestigateLandingPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-section="selector"]',
			'Landing selector section marker'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="selector"]//form',
			0,
			'Landing selector should not render lookup forms'
		);
		$this->assertXPathCount(
			$xpath,
			'//section[@data-investigate-section="lookup-shell"]',
			0,
			'Landing lookup shell marker'
		);

		foreach ( [ 'users', 'ips', 'plugins', 'themes', 'wordpress', 'requests', 'activity' ] as $subjectKey ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-investigate-subject="'.$subjectKey.'"]',
				'Landing '.$subjectKey.' subject marker'
			);
		}

		$this->assertXPathExists(
			$xpath,
			'//div[@data-investigate-subject="woocommerce" and @aria-disabled="true"]',
			'Landing woocommerce disabled marker'
		);
	}
}

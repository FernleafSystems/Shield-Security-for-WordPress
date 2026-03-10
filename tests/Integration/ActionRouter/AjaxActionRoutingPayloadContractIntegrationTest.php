<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\Render\Components\Scans\Results\Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class AjaxActionRoutingPayloadContractIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_ajax_routing_returns_rendered_html_for_ajax_render_request() :void {
		$request = ActionData::BuildAjaxRender( Wordpress::class );

		$this->applyCurrentShieldAjaxRequest( $request, false );

		$routed = $this->requireController()->action_router->action(
			AjaxRender::SLUG,
			$request,
			ActionRoutingController::ACTION_AJAX
		);
		$payload = $routed->payload();

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'html', $payload );
		$this->assertNotSame( '', \trim( (string)( $payload[ 'html' ] ?? '' ) ) );
		$this->assertSame( 200, $routed->statusCode() );
	}
}

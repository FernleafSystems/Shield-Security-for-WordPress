<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class RestActionRoutingPayloadContractIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	public function test_rest_routing_returns_payload_and_injects_success_when_missing() :void {
		$con = $this->requireController();
		$con->this_req->wp_is_ajax = false;

		$routed = $con->action_router->action(
			SecurityAdminLogin::SLUG,
			[
				'sec_admin_key' => '',
			],
			ActionRoutingController::ACTION_REST
		);
		$payload = $routed->payload();

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertSame( (bool)$routed->actionResponse()->success, (bool)$payload[ 'success' ] );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertSame( 200, $routed->statusCode() );
	}
}


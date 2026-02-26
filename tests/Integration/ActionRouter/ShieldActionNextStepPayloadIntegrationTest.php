<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldActionNextStepPayloadIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	public function test_shield_routed_payload_includes_next_step_for_plugin_redirect_flow() :void {
		$con = $this->requireController();
		$con->this_req->wp_is_ajax = false;

		$routed = $con->action_router->action(
			SecurityAdminAuthClear::SLUG,
			[],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertArrayHasKey( 'next_step', $payload );
		$this->assertSame( 'redirect', $payload[ 'next_step' ][ 'type' ] ?? '' );
		$this->assertNotSame( '', (string)( $payload[ 'next_step' ][ 'url' ] ?? '' ) );
	}
}


<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldPluginActionRouteIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( [ 'rest_api_level_1' ] );
		$this->loginAsAdministrator();
	}

	public function test_action_route_matches_live_slug_with_digits() :void {
		$request = new \WP_REST_Request( 'POST', \sprintf( '/shield/v1/action/%s', MfaLoginVerifyStep::SLUG ) );
		$request->set_param( 'payload', [] );

		$response = $this->resetRestServer()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 0, $data[ 'error_code' ] ?? null );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertFalse( (bool)( $data[ 'success' ] ?? true ) );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertSame( false, $data[ 'data' ][ 'success' ] ?? true );
		$this->assertArrayHasKey( 'page_reload', $data[ 'data' ] );
		$this->assertArrayHasKey( 'message', $data[ 'data' ] );
		$this->assertArrayHasKey( 'html', $data[ 'data' ] );
		$this->assertSame( MfaLoginVerifyStep::SLUG, $request->get_param( 'ex' ) );
	}

	private function resetRestServer() :\WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return \rest_get_server();
	}
}

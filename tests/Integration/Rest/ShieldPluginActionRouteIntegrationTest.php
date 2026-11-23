<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\MfaLoginVerifyStep,
	Actions\TestRestFetchRequests
};
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

	public function test_action_runs_through_the_route_with_its_nonce_in_the_query() :void {
		$this->setSecurityAdminContext( true );

		$data = $this->dispatchTestRestFetch( [
			ActionData::FIELD_NONCE => ActionNonce::Create( TestRestFetchRequests::class ),
		], [] );

		$this->assertTrue( (bool)( $data[ 'success' ] ?? false ) );
		$this->assertTrue( $this->testRestFetchRan() );
	}

	public function test_action_with_a_wrong_nonce_is_refused_through_the_route() :void {
		$this->setSecurityAdminContext( true );

		$data = $this->dispatchTestRestFetch( [
			ActionData::FIELD_NONCE => 'wrong_nonce',
		], [] );

		$this->assertFalse( (bool)( $data[ 'success' ] ?? true ) );
		$this->assertFalse( $this->testRestFetchRan() );
	}

	public function test_nonce_carried_only_inside_the_payload_does_not_satisfy_the_route() :void {
		$this->setSecurityAdminContext( true );

		$data = $this->dispatchTestRestFetch( [], [
			ActionData::FIELD_NONCE => ActionNonce::Create( TestRestFetchRequests::class ),
		] );

		$this->assertFalse( (bool)( $data[ 'success' ] ?? true ) );
		$this->assertFalse( $this->testRestFetchRan() );
	}

	public function test_valid_nonce_does_not_stand_in_for_security_admin_through_the_route() :void {
		$this->setSecurityAdminContext( false );

		$data = $this->dispatchTestRestFetch( [
			ActionData::FIELD_NONCE => ActionNonce::Create( TestRestFetchRequests::class ),
		], [] );

		$this->assertFalse( (bool)( $data[ 'success' ] ?? true ) );
		$this->assertFalse( $this->testRestFetchRan() );
	}

	private function dispatchTestRestFetch( array $query, array $payload ) :array {
		$this->requireController()->opts->optSet( TestRestFetchRequests::OPT_KEY, [] );

		$request = new \WP_REST_Request( 'POST', \sprintf( '/shield/v1/action/%s', TestRestFetchRequests::SLUG ) );
		$request->set_query_params( $query );
		$request->set_param( 'payload', $payload );

		$response = $this->resetRestServer()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		return $data;
	}

	private function testRestFetchRan() :bool {
		$restData = $this->requireController()->opts->optGet( TestRestFetchRequests::OPT_KEY );
		return (int)( $restData[ TestRestFetchRequests::DATA_SUCCESS_TEST_AT ] ?? 0 ) > 0;
	}

	private function resetRestServer() :\WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return \rest_get_server();
	}
}

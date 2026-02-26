<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\AjaxResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\RestApiActionResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionResponsePayloadContractTest extends BaseUnitTest {

	public function test_set_payload_success_helper_merges_success_into_payload() :void {
		$response = new ActionResponse();
		$response->setPayload( [
			'message' => 'existing',
		] );
		$response->setPayloadSuccess( true );

		$this->assertSame( [
			'message' => 'existing',
			'success' => true,
		], $response->payload() );
	}

	public function test_set_payload_redirect_next_step_helper_sets_redirect_shape() :void {
		$response = new ActionResponse();
		$response->setPayloadRedirectNextStep( '/redirect-target/' );

		$this->assertSame( [
			'type' => 'redirect',
			'url'  => '/redirect-target/',
		], $response->payload()[ 'next_step' ] ?? [] );
	}

	public function test_ajax_transport_uses_payload_success_as_canonical_source() :void {
		$response = new ActionResponse();
		$response->success = true;
		$response->setPayload( [
			'success' => false,
			'message' => 'payload-canonical',
		] );

		$payload = ( new AjaxResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( 'payload-canonical', $payload[ 'message' ] ?? '' );
	}

	public function test_ajax_transport_defaults_to_failure_when_payload_success_missing() :void {
		$response = new ActionResponse();
		$response->success = true;
		$response->setPayload( [
			'message' => 'no success key',
		] );

		$payload = ( new AjaxResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( 'no success key', $payload[ 'message' ] ?? '' );
	}

	public function test_rest_transport_does_not_promote_legacy_success_when_payload_missing() :void {
		$response = new ActionResponse();
		$response->success = true;
		$response->setPayload( [
			'message' => 'no success key',
		] );

		$payload = ( new RestApiActionResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertArrayNotHasKey( 'success', $payload );
		$this->assertSame( 'no success key', $payload[ 'message' ] ?? '' );
	}
}

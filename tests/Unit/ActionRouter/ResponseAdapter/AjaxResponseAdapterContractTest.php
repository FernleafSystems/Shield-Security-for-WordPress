<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\AjaxResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AjaxResponseAdapterContractTest extends BaseUnitTest {

	public function test_adapter_builds_normalized_payload_and_hides_internal_data() :void {
		$response = new ActionResponse();
		$response->message = 'fallback_message';
		$response->error = 'fallback_error';
		$response->action_data = [
			'ex' => 'internal',
		];
		$response->setPayload( [
			'success' => true,
			'message' => 'payload_message',
			'html'    => 'rendered_payload',
		] );

		$payload = ( new AjaxResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 'payload_message', $payload[ 'message' ] ?? '' );
		$this->assertSame( 'rendered_payload', $payload[ 'html' ] ?? '' );
		$this->assertArrayHasKey( 'error', $payload );
		$this->assertArrayHasKey( 'page_title', $payload );
		$this->assertArrayHasKey( 'page_url', $payload );
		$this->assertArrayHasKey( 'show_toast', $payload );
		$this->assertArrayNotHasKey( 'action_response_data', $payload );
		$this->assertArrayNotHasKey( 'action_data', $payload );
	}

	public function test_adapter_enforces_payload_canonical_success_when_payload_success_missing() :void {
		$response = new ActionResponse();
		$response->success = true;
		$response->message = 'fallback_message';
		$response->error = 'fallback_error';
		$response->setPayload( [
			'message' => 'payload_message',
		] );

		$payload = ( new AjaxResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( 'payload_message', $payload[ 'message' ] ?? '' );
		$this->assertSame( 'fallback_error', $payload[ 'error' ] ?? '' );
		$this->assertArrayHasKey( 'html', $payload );
		$this->assertArrayHasKey( 'page_title', $payload );
		$this->assertArrayHasKey( 'page_url', $payload );
		$this->assertArrayHasKey( 'show_toast', $payload );
		$this->assertArrayNotHasKey( 'page_reload', $payload );
	}
}

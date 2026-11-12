<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\AjaxResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AjaxResponseAdapterContractTest extends BaseUnitTest {

	public function test_adapter_builds_normalized_payload_and_hides_internal_data() :void {
		$response = new ActionResponse();
		$response->action_slug = 'internal_slug';
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
		$this->assertArrayNotHasKey( 'action_slug', $payload );
	}

	/**
	 * The payload is the whole response: properties set directly on the response object never reach the transport.
	 */
	public function test_adapter_builds_only_from_the_payload() :void {
		$response = new ActionResponse();
		$response->success = true;
		$response->message = 'loose_message';
		$response->error = 'loose_error';
		$response->setPayload( [
			'message' => 'payload_message',
		] );

		$payload = ( new AjaxResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( 'payload_message', $payload[ 'message' ] ?? '' );
		$this->assertSame( '', $payload[ 'error' ] ?? null );
		$this->assertArrayHasKey( 'html', $payload );
		$this->assertArrayHasKey( 'page_title', $payload );
		$this->assertArrayHasKey( 'page_url', $payload );
		$this->assertArrayHasKey( 'show_toast', $payload );
		$this->assertArrayNotHasKey( 'page_reload', $payload );
	}
}

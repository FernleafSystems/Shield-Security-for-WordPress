<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\RestApiActionResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RestApiActionResponseAdapterTest extends BaseUnitTest {

	public function test_adapt_returns_existing_payload_success_value() :void {
		$response = ( new ActionResponse() )
			->setPayload( [
				'success' => false,
				'message' => 'existing',
			] );
		$response->success = true;

		$routed = ( new RestApiActionResponseAdapter() )->adapt( $response );
		$payload = $routed->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertSame( 'existing', $payload[ 'message' ] ?? '' );
	}

	public function test_adapt_injects_success_when_payload_missing_success_key() :void {
		$response = ( new ActionResponse() )
			->setPayload( [
				'message' => 'no success key',
			] );
		$response->success = true;

		$routed = ( new RestApiActionResponseAdapter() )->adapt( $response );
		$payload = $routed->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 'no success key', $payload[ 'message' ] ?? '' );
		$this->assertSame( 200, $routed->statusCode() );
	}
}


<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ShieldActionResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ShieldActionResponseAdapterTest extends BaseUnitTest {

	public function test_adapt_adds_next_step_to_transport_payload_when_missing() :void {
		$response = ( new ActionResponse() )->setPayload( [
			'success' => true,
		] );
		$response->next_step = [
			'type' => 'redirect',
			'url'  => '/wp-admin/',
		];

		$payload = ( new ShieldActionResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertSame( 'redirect', $payload[ 'next_step' ][ 'type' ] ?? '' );
		$this->assertSame( '/wp-admin/', $payload[ 'next_step' ][ 'url' ] ?? '' );
	}

	public function test_adapt_preserves_payload_next_step_when_already_present() :void {
		$response = ( new ActionResponse() )->setPayload( [
			'success'   => true,
			'next_step' => [
				'type' => 'redirect',
				'url'  => '/from-payload/',
			],
		] );
		$response->next_step = [
			'type' => 'redirect',
			'url'  => '/from-response/',
		];

		$payload = ( new ShieldActionResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertSame( '/from-payload/', $payload[ 'next_step' ][ 'url' ] ?? '' );
	}
}


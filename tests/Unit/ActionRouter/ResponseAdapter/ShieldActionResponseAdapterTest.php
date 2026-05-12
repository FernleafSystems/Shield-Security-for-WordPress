<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ShieldActionResponseAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ShieldActionResponseAdapterTest extends BaseUnitTest {

	public function test_adapt_does_not_promote_legacy_next_step_property_when_payload_missing() :void {
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

		$this->assertArrayNotHasKey( 'next_step', $payload );
	}

	public function test_adapt_preserves_payload_next_step_when_present() :void {
		$response = ( new ActionResponse() )->setPayload( [
			'success'   => true,
			'next_step' => [
				'type' => 'redirect',
				'url'  => '/from-payload/',
			],
		] );

		$payload = ( new ShieldActionResponseAdapter() )
			->adapt( $response )
			->payload();

		$this->assertSame( 'redirect', $payload[ 'next_step' ][ 'type' ] ?? '' );
		$this->assertSame( '/from-payload/', $payload[ 'next_step' ][ 'url' ] ?? '' );
	}
}

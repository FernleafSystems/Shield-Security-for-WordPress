<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Services\Services;

trait ActionRequestNonceFixture {

	/**
	 * Seed canonical action transport fields into request bags used by nonce verification.
	 *
	 * @param class-string $actionClass
	 * @return array<string,array>
	 */
	private function seedActionNonceContext( string $actionClass ) :array {
		$servicesRequest = Services::Request();
		$thisRequest = self::con()->this_req->request;

		$snapshot = [
			'services_query' => \is_array( $servicesRequest->query ) ? $servicesRequest->query : [],
			'services_post'  => \is_array( $servicesRequest->post ) ? $servicesRequest->post : [],
			'this_query'     => \is_array( $thisRequest->query ) ? $thisRequest->query : [],
			'this_post'      => \is_array( $thisRequest->post ) ? $thisRequest->post : [],
		];

		$actionData = ActionData::Build( $actionClass, false );
		$transport = [
			ActionData::FIELD_ACTION  => (string)( $actionData[ ActionData::FIELD_ACTION ] ?? ActionData::FIELD_SHIELD ),
			ActionData::FIELD_EXECUTE => (string)( $actionData[ ActionData::FIELD_EXECUTE ] ?? '' ),
			ActionData::FIELD_NONCE   => (string)( $actionData[ ActionData::FIELD_NONCE ] ?? '' ),
		];

		$servicesRequest->query = \array_merge( $snapshot[ 'services_query' ], $transport );
		$servicesRequest->post = \array_merge( $snapshot[ 'services_post' ], $transport );
		$thisRequest->query = \array_merge( $snapshot[ 'this_query' ], $transport );
		$thisRequest->post = \array_merge( $snapshot[ 'this_post' ], $transport );

		return $snapshot;
	}

	/**
	 * @param array<string,array> $snapshot
	 */
	private function restoreActionNonceContext( array $snapshot ) :void {
		$servicesRequest = Services::Request();
		$thisRequest = self::con()->this_req->request;

		$servicesRequest->query = \is_array( $snapshot[ 'services_query' ] ?? null ) ? $snapshot[ 'services_query' ] : [];
		$servicesRequest->post = \is_array( $snapshot[ 'services_post' ] ?? null ) ? $snapshot[ 'services_post' ] : [];
		$thisRequest->query = \is_array( $snapshot[ 'this_query' ] ?? null ) ? $snapshot[ 'this_query' ] : [];
		$thisRequest->post = \is_array( $snapshot[ 'this_post' ] ?? null ) ? $snapshot[ 'this_post' ] : [];
	}
}

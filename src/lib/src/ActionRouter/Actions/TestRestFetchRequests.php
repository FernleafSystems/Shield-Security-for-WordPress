<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class TestRestFetchRequests extends BaseAction {

	public const SLUG = 'test_rest_fetch_requests';

	protected function exec() {
		$opts = self::con()->opts;

		$data = $opts->optGet( 'test_rest_data' );
		$data[ 'success_test_at' ] = Services::Request()->ts();
		$opts->optSet( 'test_rest_data', $data );

		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}
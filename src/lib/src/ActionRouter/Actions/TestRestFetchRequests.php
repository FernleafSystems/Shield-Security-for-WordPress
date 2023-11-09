<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class TestRestFetchRequests extends BaseAction {

	public const SLUG = 'test_rest_fetch_requests';

	protected function exec() {
		$opts = self::con()->getModule_Plugin()->opts();
		$data = $opts->getOpt( 'test_rest_data' );
		error_log( var_export( $data, true ) );
		$data[ 'success_at' ] = Services::Request()->ts();
		$opts->setOpt( 'test_rest_data', $data );

		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}
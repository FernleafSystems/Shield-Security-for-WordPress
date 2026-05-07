<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class TestRestFetchRequests extends BaseAction {

	public const SLUG = 'test_rest_fetch_requests';
	public const OPT_KEY = 'test_rest_data';
	public const DATA_MAYBE_TEST_AT = 'maybe_test_at';
	public const DATA_SUCCESS_TEST_AT = 'success_test_at';

	protected function exec() {
		$opts = self::con()->opts;

		$data = \array_merge( [
			self::DATA_MAYBE_TEST_AT   => 0,
			self::DATA_SUCCESS_TEST_AT => 0,
		], (array)$opts->optGet( self::OPT_KEY ) );
		$data[ self::DATA_SUCCESS_TEST_AT ] = Services::Request()->ts();
		$opts->optSet( self::OPT_KEY, $data );

		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}

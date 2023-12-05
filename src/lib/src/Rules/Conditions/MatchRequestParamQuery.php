<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class MatchRequestParamQuery extends MatchRequestParam {

	public const SLUG = 'match_request_param_query';

	public function getDescription() :string {
		return __( "Do any QUERY parameters in the request match the given set of parameters.", 'wp-simple-firewall' );
	}

	protected function getRequestParamsToTest() :array {
		return Services::Request()->query;
	}
}
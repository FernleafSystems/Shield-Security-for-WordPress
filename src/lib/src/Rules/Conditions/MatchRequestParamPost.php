<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class MatchRequestParamPost extends MatchRequestParam {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_param_post';

	public function getDescription() :string {
		return __( "Do any POST parameters in the request match the given set of parameters.", 'wp-simple-firewall' );
	}

	protected function getRequestParamsToTest() :array {
		return Services::Request()->post;
	}
}
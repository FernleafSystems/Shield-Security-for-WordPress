<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class MatchRequestParamPost extends MatchRequestParam {

	public const SLUG = 'match_request_param_post';

	protected function getRequestParamsToTest() :array {
		return Services::Request()->post;
	}
}
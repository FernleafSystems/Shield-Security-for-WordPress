<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class MatchRequestParamQuery extends MatchRequestParam {

	public const SLUG = 'match_request_param_query';

	protected function getRequestParamsToTest() :array {
		return Services::Request()->query;
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

/**
 * @deprecated 18.6
 */
class MatchRequestParamQuery extends MatchRequestParam {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_param_query';

	public function getDescription() :string {
		return __( "Do any QUERY parameters in the request match the given set of parameters.", 'wp-simple-firewall' );
	}

	protected function getRequestParamsToTest() :array {
		return $this->req->request->query;
	}
}
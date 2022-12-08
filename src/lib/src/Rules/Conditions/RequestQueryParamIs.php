<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestQueryParamIs extends RequestParamIs {

	public const SLUG = 'request_query_param_is';

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return Services::Request()->query( $this->match_param );
	}
}
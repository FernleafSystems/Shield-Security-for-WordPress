<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestQueryParamIs extends RequestParamIs {

	public const SLUG = 'request_query_param_is';

	public function getDescription() :string {
		return __( 'Does the value of the given QUERY request parameter match against the given patterns.', 'wp-simple-firewall' );
	}

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return Services::Request()->query( $this->match_param );
	}
}
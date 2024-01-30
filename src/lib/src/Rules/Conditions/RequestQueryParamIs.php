<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

/**
 * @deprecated 18.6
 */
class RequestQueryParamIs extends RequestParamIs {

	use Traits\TypeRequest;

	public const SLUG = 'request_query_param_is';

	public function getDescription() :string {
		return __( 'Does the value of the given QUERY request parameter match against the given patterns.', 'wp-simple-firewall' );
	}

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return null;
	}
}
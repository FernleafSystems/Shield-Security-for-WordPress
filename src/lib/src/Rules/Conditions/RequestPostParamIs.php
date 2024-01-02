<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

/**
 * @deprecated 18.6
 */
class RequestPostParamIs extends RequestParamIs {

	use Traits\TypeRequest;

	public const SLUG = 'request_post_param_is';

	public function getDescription() :string {
		return __( 'Does the value of the given POST request parameter match against the given patterns.', 'wp-simple-firewall' );
	}

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return null;
	}
}
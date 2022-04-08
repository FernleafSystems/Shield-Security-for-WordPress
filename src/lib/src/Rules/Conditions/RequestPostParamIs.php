<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestPostParamIs extends RequestParamIs {

	const SLUG = 'request_post_param_is';

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return Services::Request()->post( $this->match_param );
	}
}
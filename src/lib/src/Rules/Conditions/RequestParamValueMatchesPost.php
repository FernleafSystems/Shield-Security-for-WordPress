<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestParamValueMatchesPost extends RequestParamValueMatchesBase {

	public function getDescription() :string {
		return __( 'Does the value of the given Query request parameter match the given pattern.', 'wp-simple-firewall' );
	}

	protected function getRequestParamValue() :string {
		return (string)Services::Request()->post( $this->param_name );
	}
}
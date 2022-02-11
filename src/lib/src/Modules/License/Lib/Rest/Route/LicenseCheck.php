<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

class LicenseCheck extends Base {

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Request\LicenseCheck::class;
	}
}
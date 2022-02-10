<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Request;

class AddIP extends Base {

	public function getArgMethods() :array {
		return [ \WP_REST_Server::CREATABLE ];
	}

	protected function getRequestProcessorClass() :string {
		return Request\AddIP::class;
	}

	public function getRoutePath() :string {
		return '/[bypass|block]';
	}
}
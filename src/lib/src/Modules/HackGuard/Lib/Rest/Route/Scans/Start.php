<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Scans;

class Start extends ScansBase {

	const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRequestProcessorClass() :string {
		return Scans\Start::class;
	}

	protected function getRouteArgsCustom() :array {
		return [
			'scan_slugs' => $this->getRouteArgSchema( 'scan_slugs' ),
		];
	}
}
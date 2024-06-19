<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Scans;

class Start extends ScansBase {

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Scans\Start::class;
	}

	protected function getRouteArgsCustom() :array {
		return [
			'scan_slugs' => $this->getRouteArgSchema( 'scan_slugs' ),
		];
	}
}
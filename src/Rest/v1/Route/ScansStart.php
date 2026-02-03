<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class ScansStart extends ScansBase {

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ScansStart::class;
	}

	protected function getRouteArgsCustom() :array {
		return [
			'scan_slugs' => $this->getRouteArgSchema( 'scan_slugs' ),
		];
	}
}
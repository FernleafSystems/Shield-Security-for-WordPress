<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Options;

class SetSingle extends BaseSingle {

	public const ROUTE_METHOD = \WP_REST_Server::EDITABLE;

	protected function getRouteArgsCustom() :array {
		return [
			'value' => $this->getRouteArgSchema( 'value' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options\SetSingle::class;
	}
}
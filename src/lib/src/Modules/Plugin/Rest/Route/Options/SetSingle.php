<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class SetSingle extends BaseSingle {

	public const ROUTE_METHOD = \WP_REST_Server::EDITABLE;

	protected function getRouteArgsCustom() :array {
		return [
			'value' => $this->getRouteArgSchema( 'value' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetSingle::class;
	}
}
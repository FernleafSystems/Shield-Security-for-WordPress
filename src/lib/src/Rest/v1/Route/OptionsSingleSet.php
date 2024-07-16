<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class OptionsSingleSet extends OptionsSingleBase {

	public const ROUTE_METHOD = \WP_REST_Server::EDITABLE;

	protected function getRouteArgsCustom() :array {
		return [
			'value' => $this->getRouteArgSchema( 'value' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\OptionsSingleSet::class;
	}
}
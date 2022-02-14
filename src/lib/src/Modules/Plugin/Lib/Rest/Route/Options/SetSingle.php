<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetSingle extends BaseSingle {

	protected function getRouteArgsCustom() :array {
		return [
			'value' => $this->getPropertySchema( 'value' ),
		];
	}

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetSingle::class;
	}
}
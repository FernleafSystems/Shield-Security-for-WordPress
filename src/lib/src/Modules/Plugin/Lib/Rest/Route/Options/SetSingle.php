<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetSingle extends BaseSingle {

	protected function getRouteArgsCustom() :array {
		return [
			'value' => $this->getPropertySchema( 'value' ),
		];
	}

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'value':
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return true;
	}

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetSingle::class;
	}
}
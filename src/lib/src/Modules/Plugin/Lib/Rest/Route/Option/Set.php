<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Option;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Option;

class Set extends Base {

	protected function getRouteArgsCustom() :array {
		return [
			'option' => [
				'description' => 'Array of option data to set. Must include option key, value and mod(ule)',
				'type'        => 'object',
				'required'    => true,
				'properties'  => [
					'mod'   => [
						'description' => "The module slug that's home to the option key.",
						'type'        => 'string',
						'required'    => true,
					],
					'key'   => [
						'description' => "The option key.",
						'type'        => 'string',
						'required'    => true,
					],
					'value' => [
						'description' => "The new option value.",
						'required'    => true,
					],
				],
			],
		];
	}

	public function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

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
		return Option\Set::class;
	}
}
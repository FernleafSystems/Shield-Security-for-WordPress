<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Exceptions\OptionDoesNotExistException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetBulk extends BaseBulk {

	protected function getRouteArgsCustom() :array {
		return [
			'options' => [
				'description' => 'Array of options to set. Each must include option key and value',
				'required'    => true,
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'key'   => $this->getPropertySchema( 'key' ),
						'value' => $this->getPropertySchema( 'value' ),
					],
				],
			],
		];
	}

	/**
	 * @param string|mixed $value
	 * @return \WP_Error|true
	 * @throws \Exception
	 */
	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'options':
				foreach ( $value as $option ) {
					if ( !is_array( $option ) ) {
						throw new \Exception( "Each option in the 'options' parameter must be an array." );
					}
					$optKey = $option[ 'key' ];
					if ( !$this->optKeyExists( $optKey ) ) {
						throw new OptionDoesNotExistException( sprintf( "Option with key '%s' doesn't exist.", $optKey ) );
					}
				}
				break;
		}
		return true;
	}

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetBulk::class;
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class Set extends Base {

	protected function getRouteArgsCustom() :array {
		return [
			'options' => [
				'description' => 'Array of options to set. Each must include option key, value and module',
				'type'        => 'object',
				'required'    => true,
			],
		];
	}

	/**
	 * @param string|mixed $value
	 * @return \WP_Error|true
	 * @throws \Exception
	 */
	public function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {
		$con = $this->getCon();
		if ( !is_array( $value ) ) {
			throw new \Exception( 'Options parameter is not of the correct type (array)' );
		}

		$requiredKeys = array_flip( [
			'key',
			'value',
			'mod'
		] );
		foreach ( $value as $option ) {
			if ( count( array_intersect_key( $option, $requiredKeys ) ) !== 3 ) {
				throw new \Exception( "One of the options doesn't contain the necessary keys" );
			}
			if ( !isset( $con->modules[ $option[ 'mod' ] ] ) ) {
				throw new \Exception( sprintf( "One of the options specifies a module that doesn't exist: %s", $option[ 'mod' ] ) );
			}
			$optKey = $option[ 'key' ];
			$mod = $con->modules[ $option[ 'mod' ] ];
			$modOpts = $mod->getOptions();
			if ( !in_array( $optKey, $modOpts->getOptionsKeys() ) ) {
				throw new \Exception( sprintf( "One of the options specifies a option key that doesn't exist: %s", $optKey ) );
			}
			$optVal = $option[ 'val' ];
			// TODO: validation option value type.
		}

		return true;
	}

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Options\Set::class;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptValueSanitize {

	use ModConsumer;

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( $key, $value ) {
		$opts = $this->getOptions();
		if ( !in_array( $key, $opts->getOptionsKeys() ) ) {
			throw new \Exception( sprintf( 'Not a valid option key for module: %s', $key ) );
		}

		$validValue = false;
		switch ( $opts->getOptionType( $key ) ) {

			case 'boolean':
				$validValue = is_bool( $value );
				break;

			case 'integer':
				$validValue = is_numeric( $value );
				if ( $validValue ) {
					$value = (int)$value;
				}
				break;

			case 'email':
				$value = trim( (string)$value );
				$validValue = empty( $value ) || Services::Data()->validEmail( $value );
				break;

			case 'array':
				$validValue = is_array( $value );
				break;

			case 'text':
				if ( is_null( $value ) || is_scalar( $value ) ) {
					$validValue = true;
					$value = (string)$value;
				}
				break;

			case 'select':
				$validValue = is_string( $value ) && strlen( $value ) > 0;
				break;

			case 'multiple_select':
				if ( is_array( $value ) ) {
					$validValue = count( array_diff(
							$value,
							array_map(
								function ( $aValueOption ) {
									return $aValueOption[ 'value_key' ];
								},
								$opts->getOptDefinition( $key )[ 'value_options' ]
							)
						) ) === 0;
				}
				break;

			case 'checkbox':
				if ( is_string( $value ) ) {
					$value = strtoupper( $value );
					$validValue = in_array( $value, [ 'Y', 'N' ] );
				}
				break;

			default:
				$validValue = true;
				break;
		}

		if ( !$validValue ) {
			throw new \Exception( 'Not a valid value type for option.' );
		}

		return $value;
	}
}
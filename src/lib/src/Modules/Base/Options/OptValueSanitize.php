<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptValueSanitize {

	use ModConsumer;

	/**
	 * @param string $sKey
	 * @param mixed  $mVal
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( $sKey, $mVal ) {
		$opts = $this->getOptions();
		$raw = $opts->getRawData_SingleOption( $sKey );

		if ( !in_array( $sKey, $opts->getOptionsKeys() ) ) {
			throw new \Exception( sprintf( 'Not a valid option key for module: %s', $sKey ) );
		}

		$validValue = false;
		switch ( $opts->getOptionType( $sKey ) ) {

			case 'boolean':
				$validValue = is_bool( $mVal );
				break;

			case 'integer':
				$validValue = is_numeric( $mVal );
				if ( $validValue ) {
					$mVal = (int)$mVal;
				}
				break;

			case 'email':
				$mVal = trim( (string)$mVal );
				$validValue = empty( $mVal ) || Services::Data()->validEmail( $mVal );
				break;

			case 'array':
				$validValue = is_array( $mVal );
				break;

			case 'text':
				if ( is_null( $mVal ) || is_scalar( $mVal ) ) {
					$validValue = true;
					$mVal = (string)$mVal;
				}
				break;

			case 'select':
				$validValue = is_string( $mVal ) && strlen( $mVal ) > 0;
				break;

			case 'multiple_select':
				if ( is_array( $mVal ) ) {
					$validValue = count( array_diff(
							$mVal,
							array_map(
								function ( $aValueOption ) {
									return $aValueOption[ 'value_key' ];
								},
								$raw[ 'value_options' ]
							)
						) ) === 0;
				}
				break;

			case 'checkbox':
				if ( is_string( $mVal ) ) {
					$mVal = strtoupper( $mVal );
					$validValue = in_array( $mVal, [ 'Y', 'N' ] );
				}
				break;

			default:
				$validValue = true;
				break;
		}

		if ( !$validValue ) {
			throw new \Exception( 'Not a valid value type for option.' );
		}

		return $mVal;
	}
}
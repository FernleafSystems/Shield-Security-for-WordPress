<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptValueSanitize {

	use ModConsumer;

	/**
	 * @param mixed $value
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( string $key, $value ) {
		$opts = $this->getOptions();
		if ( !\in_array( $key, $opts->getOptionsKeys() ) ) {
			throw new \Exception( sprintf( 'Not a valid option key for module: %s', $key ) );
		}

		$isValid = false;
		switch ( $opts->getOptionType( $key ) ) {

			case 'boolean':
				$isValid = \is_bool( $value );
				break;

			case 'integer':
				$isValid = \is_numeric( $value );
				if ( $isValid ) {
					$value = (int)$value;
				}
				break;

			case 'email':
				$value = \trim( (string)$value );
				$isValid = empty( $value ) || Services::Data()->validEmail( $value );
				break;

			case 'array':
				$isValid = \is_array( $value );
				break;

			case 'text':
				if ( \is_null( $value ) || \is_scalar( $value ) ) {
					$isValid = true;
					$value = (string)$value;
				}
				break;

			case 'select':
				$isValid = \is_string( $value ) && \strlen( $value ) > 0;
				break;

			case 'multiple_select':
				if ( \is_array( $value ) ) {
					$isValid = \count( \array_diff(
							$value,
							\array_map(
								function ( $aValueOption ) {
									return $aValueOption[ 'value_key' ];
								},
								$opts->getOptDefinition( $key )[ 'value_options' ]
							)
						) ) === 0;
				}
				break;

			case 'checkbox':
				if ( \is_string( $value ) ) {
					$value = \strtoupper( $value );
					$isValid = \in_array( $value, [ 'Y', 'N' ] );
				}
				break;

			default:
				$isValid = true;
				break;
		}

		if ( !$isValid ) {
			throw new \Exception( 'Not a valid value type for option.' );
		}

		return $value;
	}
}
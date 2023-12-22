<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	ParameterIncorrectTypeException,
	ParametersMissingException
};
use IPLib\Factory;

class VerifyParams {

	/**
	 * @param mixed $paramValue
	 * @return mixed
	 * @throws \Exception
	 */
	public function verifyParam( $paramValue, array $def, string $paramName = '' ) {
		$default = $def[ 'default' ] ?? null;
		if ( $paramValue === null ) {
			$paramValue = $default;
		}

		if ( $paramValue === null ) {
			throw new \Exception( 'Please provide a value' );
		}

		if ( \is_string( $paramValue ) ) {
			$paramValue = \trim( $paramValue );
		}

		switch ( $def[ 'type' ] ) {
			case EnumParameters::TYPE_CALLBACK:
				$invalidType = !\is_callable( $paramValue );
				$invalidMsg = __( "Provide a valid callable function", 'wp-simple-firewall' );
				break;
			case EnumParameters::TYPE_INT:
				$invalidType = !\preg_match( '#^\d+$#', (string)$paramValue );
				if ( !$invalidType ) {
					$paramValue = (int)$paramValue;
				}
				$invalidMsg = __( 'Provide a valid whole number', 'wp-simple-firewall' );
				break;
			case EnumParameters::TYPE_IP_ADDRESS:
				$invalidType = empty( Factory::parseRangeString( $paramValue ) );
				$invalidMsg = __( 'Provide a valid IP address or range', 'wp-simple-firewall' );
				break;
			case EnumParameters::TYPE_URL:
				$invalidType = \filter_var( $paramValue, FILTER_VALIDATE_URL ) === false;
				$invalidMsg = __( 'Provide a complete URL', 'wp-simple-firewall' );
				break;
			case EnumParameters::TYPE_SCALAR:
				$invalidType = !\is_scalar( $paramValue );
				break;
			case EnumParameters::TYPE_ENUM:
				$invalidType = empty( $def[ 'type_enum' ] ) || !\in_array( $paramValue, $def[ 'type_enum' ] );
				$invalidMsg = __( 'Please select one of the options available', 'wp-simple-firewall' );
				break;
			case EnumParameters::TYPE_BOOL:
				/**
				 * Special case. To avoid type distortions with checkboxes in forms, we use 'Y' & 'N' to submit
				 * parameter values for booleans. We must account for this during verification of the parameter
				 * and also when outputting the <input> values.
				 */
				$invalidType = !\is_bool( $paramValue );
				if ( $invalidType && \in_array( $paramValue, [ 'Y', 'N' ] ) ) {
					$paramValue = $paramValue === 'Y';
					$invalidType = false;
				}
				break;
			default:
				$invalidType = \gettype( $paramValue ) !== $def[ 'type' ];
				$invalidMsg = __( 'Not a valid value for this parameter', 'wp-simple-firewall' );
				break;
		}

		if ( $invalidType ) {
			throw new ParameterIncorrectTypeException( empty( $invalidMsg ) ? sprintf( 'Incorrect parameter type: %s, %s, %s, %s',
				$paramName,
				var_export( $paramValue, true ),
				\gettype( $paramValue ),
				var_export( $def, true )
			) : $invalidMsg );
		}

		if ( $def[ 'type' ] === EnumParameters::TYPE_STRING ) {
			if ( $paramValue === '' && !isset( $def[ 'default' ] ) ) {
				throw new \Exception( 'Please provide a value' );
			}
		}

		if ( $def[ 'type' ] === EnumParameters::TYPE_STRING && !empty( $def[ 'verify_regex' ] ) ) {
			if ( !\preg_match( $def[ 'verify_regex' ], $paramValue ) ) {
				throw new \Exception( sprintf( 'Please ensure only valid characters (value: %s) (regex: %s)', $paramValue, $def[ 'verify_regex' ] ) );
			}
		}

		return $paramValue;
	}

	/**
	 * @throws ParametersMissingException
	 * @throws \Exception
	 */
	public function verifyParams( array $params = [], array $paramsDef = [] ) {
		foreach ( $paramsDef as $key => $def ) {
			$this->verifyParam( $params[ $key ] ?? null, $def, $key );
		}
	}
}
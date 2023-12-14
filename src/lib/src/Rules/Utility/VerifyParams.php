<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	ParameterIncorrectTypeException,
	ParametersMissingException
};

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
			case 'callback':
				$invalidType = !\is_callable( $paramValue );
				break;
			case 'int':
				$invalidType = !\is_numeric( $paramValue ) || !\preg_match( '#^\d+$#', (string)$paramValue );
				if ( !$invalidType ) {
					$paramValue = (int)$paramValue;
				}
				break;
			case 'scalar':
				$invalidType = !\is_scalar( $paramValue );
				break;
			case 'bool':
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
				break;
		}

		if ( $invalidType ) {
			throw new ParameterIncorrectTypeException( sprintf( 'Incorrect parameter type: %s, %s, %s, %s',
				$paramName,
				var_export( $paramValue, true ),
				\gettype( $paramValue ),
				var_export( $def, true )
			) );
		}

		if ( $def[ 'type' ] === 'string' ) {
			if ( $paramValue === '' ) {
				throw new \Exception( 'Please provide a value' );
			}
		}

		if ( $def[ 'type' ] === 'string' && !empty( $def[ 'verify_regex' ] ) ) {
			if ( !\preg_match( $def[ 'verify_regex' ], $paramValue ) ) {
				throw new \Exception( sprintf( 'Please ensure only valid characters (%s)', $def[ 'verify_regex' ] ) );
			}
		}

		return $paramValue;
	}

	/**
	 * @throws ParameterIncorrectTypeException
	 * @throws ParametersMissingException
	 */
	public function verifyParams( array $params = [], array $paramsDef = [] ) {

		$missing = \array_keys( \array_diff_key( $paramsDef, $params ) );
		if ( !empty( $missing ) ) {
			throw new ParametersMissingException( sprintf( 'Parameter missing for response handler: %s', var_export( $missing, true ) ) );
		}

		foreach ( $paramsDef as $key => $def ) {
			$type = $def[ 'type' ] ?? null;
			if ( !empty( $type ) ) {
				switch ( $type ) {
					case 'callback':
						$invalidType = !\is_callable( $params[ $key ] );
						break;
					case 'scalar':
						$invalidType = !\is_scalar( $params[ $key ] );
						break;
					default:
						$invalidType = gettype( $params[ $key ] ) !== $def[ 'type' ];
						break;
				}
				if ( $invalidType ) {
					throw new ParameterIncorrectTypeException( sprintf( 'Incorrect parameter type: %s, %s, %s, %s',
						$key,
						var_export( $params[ $key ], true ),
						gettype( $params[ $key ] ),
						var_export( $def, true )
					) );
				}
			}
		}
	}
}
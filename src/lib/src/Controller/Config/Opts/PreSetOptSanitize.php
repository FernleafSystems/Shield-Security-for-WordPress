<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PreSetOptSanitize {

	use PluginControllerConsumer;

	private $key;

	private $value;

	public function __construct( string $key, $value ) {
		$this->key = $key;
		$this->value = $value;
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function run() {
		$this->exists();
		$this->validateType();
		$this->validateScope();
		$this->specificOptChecks();
		return $this->value;
	}

	/**
	 * @throws \Exception
	 */
	public function validateScope() :void {
		$valid = true;

		$optDef = self::con()->opts->optDef( $this->key );
		switch ( $optDef[ 'type' ] ) {

			case 'integer':
				$min = $optDef[ 'min' ] ?? null;
				if ( $min !== null && $this->value < $min ) {
					$this->value = $min;
				}
				$max = $optDef[ 'max' ] ?? null;
				if ( $max !== null && $this->value > $max ) {
					$this->value = $max;
				}
				break;

			case 'select':
				$valid = \in_array( $this->value, \array_map(
					function ( $valueOpt ) {
						return $valueOpt[ 'value_key' ];
					},
					$optDef[ 'value_options' ] ?? []
				) );
				break;
		}

		if ( !$valid ) {
			throw new \Exception( sprintf( 'Invalid value scope for %s', $this->key ) );
		}
	}

	/**
	 * @throws \Exception
	 */
	public function specificOptChecks() :void {
		switch ( $this->key ) {
			case 'auto_clean':
			case 'audit_trail_auto_clean':
				if ( $this->value > self::con()->caps->getMaxLogRetentionDays() ) {
					throw new \Exception( 'Cannot set log retentions days to anything longer than max' );
				}
				break;
			default:
				break;
		}
	}

	/**
	 * @throws \Exception
	 */
	public function exists() :void {
		if ( !isset( self::con()->cfg->configuration->options[ $this->key ] ) ) {
			throw new \Exception( sprintf( 'Not a valid option key for module: %s', $this->key ) );
		}
	}

	/**
	 * @throws \Exception
	 */
	public function validateType() :void {

		$valid = false;
		switch ( self::con()->opts->optType( $this->key ) ) {
			case 'boolean':
				$valid = \is_bool( $this->value );
				break;
			case 'integer':
				$valid = \is_numeric( $this->value );
				if ( $valid ) {
					$this->value = (int)$this->value;
				}
				break;
			case 'email':
				$value = \trim( (string)$this->value );
				$valid = empty( $value ) || Services::Data()->validEmail( $value );
				break;
			case 'array':
				$valid = \is_array( $this->value );
				break;
			case 'text':
				if ( \is_null( $this->value ) || \is_scalar( $this->value ) ) {
					$valid = true;
					$this->value = \trim( (string)$this->value );
				}
				break;
			case 'select':
				$valid = \is_string( $this->value ) && \strlen( $this->value ) > 0;
				break;
			case 'multiple_select':
				if ( \is_array( $this->value ) ) {
					$valid = \count( \array_diff(
							$this->value,
							\array_map(
								function ( $aValueOption ) {
									return $aValueOption[ 'value_key' ];
								},
								self::con()->opts->optDef( $this->key )[ 'value_options' ]
							)
						) ) === 0;
				}
				break;
			case 'checkbox':
				if ( \is_string( $this->value ) ) {
					$this->value = \strtoupper( $this->value );
					$valid = \in_array( $this->value, [ 'Y', 'N' ] );
				}
				break;
			default:
				$valid = true;
				break;
		}

		if ( !$valid ) {
			throw new \Exception( sprintf( 'Invalid value type for %s', $this->key ) );
		}
	}
}
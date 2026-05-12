<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

class PluginBadgeMode {

	public const DISABLED = 'disabled';
	public const LIGHT = 'light';
	public const DARK = 'dark';

	public const VALID_MODES = [
		self::DISABLED,
		self::LIGHT,
		self::DARK,
	];

	public const ENABLED_MODES = [
		self::LIGHT,
		self::DARK,
	];

	/**
	 * @param mixed $value
	 */
	public static function normalise( $value ) :string {
		$value = \is_scalar( $value ) ? \strtolower( \trim( (string)$value ) ) : '';

		if ( $value === 'y' ) {
			$value = self::LIGHT;
		}
		elseif ( $value === 'n' ) {
			$value = self::DISABLED;
		}

		return self::isValid( $value ) ? $value : self::DISABLED;
	}

	public static function isValid( string $mode ) :bool {
		return \in_array( $mode, self::VALID_MODES, true );
	}

	public static function isEnabled( string $mode ) :bool {
		return \in_array( $mode, self::ENABLED_MODES, true );
	}

	public static function renderMode( string $mode ) :string {
		return self::isEnabled( $mode ) ? $mode : self::LIGHT;
	}

	/**
	 * @return array<int,array{value_key:string,text:string}>
	 */
	public static function selectOptions() :array {
		return [
			[
				'value_key' => self::DISABLED,
				'text'      => __( 'Disabled', 'wp-simple-firewall' ),
			],
			[
				'value_key' => self::LIGHT,
				'text'      => __( 'Light', 'wp-simple-firewall' ),
			],
			[
				'value_key' => self::DARK,
				'text'      => __( 'Dark', 'wp-simple-firewall' ),
			],
		];
	}
}

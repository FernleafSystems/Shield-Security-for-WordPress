<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyBase64Url;

class PasskeyFixtureLoader {

	private static ?array $fixture = null;

	public static function load() :array {
		if ( self::$fixture === null ) {
			$fixturesDir = \dirname( \dirname( \dirname( __DIR__ ) ) ).'/fixtures/passkeys';
			$localPath = $fixturesDir.'/fixture_ceremony.local.json';
			$path = \is_file( $localPath ) ? $localPath : $fixturesDir.'/fixture_ceremony.json';
			$content = \file_get_contents( $path );
			if ( $content === false ) {
				throw new \UnexpectedValueException( sprintf( 'Unable to read passkey fixture: %s', $path ) );
			}

			$data = \json_decode( $content, true );
			if ( !\is_array( $data ) ) {
				throw new \UnexpectedValueException( sprintf(
					'Passkey fixture must decode to an array: %s (%s)',
					$path,
					\json_last_error_msg()
				) );
			}
			self::$fixture = $data;
		}

		return self::$fixture;
	}

	public static function credentialId() :string {
		return self::stringAt( 'credential', 'id' );
	}

	public static function credentialUniqueId() :string {
		return self::stringAt( 'credential', 'unique_id' );
	}

	public static function registrationCredentialId() :string {
		return self::stringAt( 'registration', 'credential', 'id' );
	}

	public static function registrationCredentialUniqueId() :string {
		return self::stringAt( 'registration', 'credential', 'unique_id' );
	}

	public static function legacyRecord() :array {
		return self::arrayAt( 'legacy_record' );
	}

	public static function legacyRecordCounter() :int {
		return self::intAt( 'legacy_record', 'counter' );
	}

	public static function registrationOptions() :array {
		return self::arrayAt( 'registration', 'options' );
	}

	public static function registrationExpectedCounter() :int {
		return self::intAt( 'registration', 'verified_record', 'counter' );
	}

	public static function authenticationOptions() :array {
		return self::arrayAt( 'authentication', 'options' );
	}

	public static function authenticationExpectedCounter() :int {
		return self::intAt( 'authentication', 'verified_record', 'counter' );
	}

	public static function registrationResponse( array $overrides = [], array $clientDataOverrides = [] ) :string {
		return self::encodeResponse(
			self::arrayAt( 'registration', 'response' ),
			$overrides,
			$clientDataOverrides
		);
	}

	public static function authenticationResponse( array $overrides = [], array $clientDataOverrides = [] ) :string {
		return self::encodeResponse(
			self::arrayAt( 'authentication', 'response' ),
			$overrides,
			$clientDataOverrides
		);
	}

	public static function requestHost() :string {
		return self::stringAt( 'meta', 'rp_id' );
	}

	public static function requestScheme() :string {
		return self::stringAt( 'meta', 'request_scheme' );
	}

	public static function userHandleRaw() :string {
		return self::stringAt( 'meta', 'user_handle_raw' );
	}

	private static function encodeResponse( array $response, array $overrides, array $clientDataOverrides ) :string {
		$response = \array_replace_recursive( $response, $overrides );
		if ( !empty( $clientDataOverrides ) ) {
			$clientData = self::decodeClientData(
				self::stringFromArray( $response, [ 'response', 'clientDataJSON' ] )
			);
			$response[ 'response' ][ 'clientDataJSON' ] = self::encodeClientData(
				\array_replace_recursive( $clientData, $clientDataOverrides )
			);
		}

		return self::jsonEncode( $response );
	}

	private static function decodeClientData( string $encoded ) :array {
		$data = \json_decode( PasskeyBase64Url::decode( $encoded ), true );
		if ( !\is_array( $data ) ) {
			throw new \UnexpectedValueException( 'Passkey fixture clientDataJSON must decode to an array.' );
		}

		return $data;
	}

	private static function encodeClientData( array $clientData ) :string {
		return PasskeyBase64Url::encode( self::jsonEncode( $clientData ) );
	}

	private static function jsonEncode( array $data ) :string {
		$encoded = \wp_json_encode( $data );
		if ( !\is_string( $encoded ) ) {
			throw new \UnexpectedValueException( 'Passkey fixture data could not be JSON encoded.' );
		}

		return $encoded;
	}

	private static function stringAt( string ...$path ) :string {
		return self::stringFromArray( self::load(), $path );
	}

	private static function intAt( string ...$path ) :int {
		$value = self::valueFromArray( self::load(), $path );
		if ( !\is_int( $value ) ) {
			throw new \UnexpectedValueException( sprintf(
				'Passkey fixture path must be an integer: %s',
				\implode( '.', $path )
			) );
		}

		return $value;
	}

	private static function arrayAt( string ...$path ) :array {
		$value = self::valueFromArray( self::load(), $path );
		if ( !\is_array( $value ) ) {
			throw new \UnexpectedValueException( sprintf(
				'Passkey fixture path must be an array: %s',
				\implode( '.', $path )
			) );
		}

		return $value;
	}

	private static function stringFromArray( array $source, array $path ) :string {
		$value = self::valueFromArray( $source, $path );
		if ( !\is_string( $value ) || $value === '' ) {
			throw new \UnexpectedValueException( sprintf(
				'Passkey fixture path must be a non-empty string: %s',
				\implode( '.', $path )
			) );
		}

		return $value;
	}

	private static function valueFromArray( array $source, array $path ) {
		$value = $source;
		foreach ( $path as $key ) {
			if ( !\is_array( $value ) || !\array_key_exists( $key, $value ) ) {
				throw new \UnexpectedValueException( sprintf(
					'Passkey fixture path is missing: %s',
					\implode( '.', $path )
				) );
			}
			$value = $value[ $key ];
		}

		return $value;
	}
}

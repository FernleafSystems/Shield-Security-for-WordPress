<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support;

use Base64Url\Base64Url;

class PasskeyFixtureLoader {

	private static ?array $fixture = null;

	public static function load() :array {
		if ( self::$fixture === null ) {
			$fixturesDir = \dirname( \dirname( \dirname( __DIR__ ) ) ).'/fixtures/passkeys';
			$localPath = $fixturesDir.'/fixture_ceremony.local.json';
			$path = \is_file( $localPath ) ? $localPath : $fixturesDir.'/fixture_ceremony.json';
			$data = \json_decode( (string)\file_get_contents( $path ), true );
			self::$fixture = \is_array( $data ) ? $data : [];
		}

		return self::$fixture;
	}

	public static function credentialId() :string {
		return (string)( self::load()[ 'credential' ][ 'id' ] ?? '' );
	}

	public static function credentialUniqueId() :string {
		return (string)( self::load()[ 'credential' ][ 'unique_id' ] ?? '' );
	}

	public static function registrationCredentialId() :string {
		return (string)( self::load()[ 'registration' ][ 'credential' ][ 'id' ] ?? '' );
	}

	public static function registrationCredentialUniqueId() :string {
		return (string)( self::load()[ 'registration' ][ 'credential' ][ 'unique_id' ] ?? '' );
	}

	public static function legacyRecord() :array {
		return (array)( self::load()[ 'legacy_record' ] ?? [] );
	}

	public static function legacyRecordCounter() :int {
		return (int)( self::legacyRecord()[ 'counter' ] ?? 0 );
	}

	public static function registrationOptions() :array {
		return (array)( self::load()[ 'registration' ][ 'options' ] ?? [] );
	}

	public static function registrationExpectedCounter() :int {
		return (int)( self::load()[ 'registration' ][ 'verified_record' ][ 'counter' ] ?? 0 );
	}

	public static function authenticationOptions() :array {
		return (array)( self::load()[ 'authentication' ][ 'options' ] ?? [] );
	}

	public static function authenticationExpectedCounter() :int {
		return (int)( self::load()[ 'authentication' ][ 'verified_record' ][ 'counter' ] ?? 0 );
	}

	public static function registrationResponse( array $overrides = [], array $clientDataOverrides = [] ) :string {
		return self::encodeResponse(
			(array)( self::load()[ 'registration' ][ 'response' ] ?? [] ),
			$overrides,
			$clientDataOverrides
		);
	}

	public static function authenticationResponse( array $overrides = [], array $clientDataOverrides = [] ) :string {
		return self::encodeResponse(
			(array)( self::load()[ 'authentication' ][ 'response' ] ?? [] ),
			$overrides,
			$clientDataOverrides
		);
	}

	public static function requestHost() :string {
		return (string)( self::load()[ 'meta' ][ 'rp_id' ] ?? '' );
	}

	public static function requestScheme() :string {
		return (string)( self::load()[ 'meta' ][ 'request_scheme' ] ?? 'https' );
	}

	public static function userHandleRaw() :string {
		return (string)( self::load()[ 'meta' ][ 'user_handle_raw' ] ?? '' );
	}

	private static function encodeResponse( array $response, array $overrides, array $clientDataOverrides ) :string {
		$response = \array_replace_recursive( $response, $overrides );
		if ( !empty( $clientDataOverrides ) ) {
			$clientData = self::decodeClientData(
				(string)( $response[ 'response' ][ 'clientDataJSON' ] ?? '' )
			);
			$response[ 'response' ][ 'clientDataJSON' ] = self::encodeClientData(
				\array_replace_recursive( $clientData, $clientDataOverrides )
			);
		}

		return \wp_json_encode( $response );
	}

	private static function decodeClientData( string $encoded ) :array {
		$data = \json_decode( Base64Url::decode( $encoded ), true );
		return \is_array( $data ) ? $data : [];
	}

	private static function encodeClientData( array $clientData ) :string {
		return Base64Url::encode( (string)\wp_json_encode( $clientData ) );
	}
}

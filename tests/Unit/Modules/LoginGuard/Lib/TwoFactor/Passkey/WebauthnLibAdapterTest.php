<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use Assert\AssertionFailedException;
use Base64Url\Base64Url;
use Brain\Monkey\Functions;
use CBOR\{
	ByteStringObject,
	Decoder,
	MapObject,
	OtherObject\OtherObjectManager,
	StringStream,
	Tag\TagObjectManager,
	TextStringObject
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey\{
	PasskeyAdapterContext,
	WebauthnLibAdapter
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\PasskeyFixtureLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey\Support\BuildsPasskeyKeys;
use Webauthn\{
	PublicKeyCredentialSource,
	PublicKeyCredentialUserEntity
};

class WebauthnLibAdapterTest extends BaseUnitTest {

	use BuildsPasskeyKeys;

	private const ALG_RS256 = -257;
	private const EXPONENT_65537 = "\x01\x00\x01";

	private array $serverSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias(
			static function ( $value ) :string {
				return (string)\json_encode( $value );
			}
		);

		$this->serverSnapshot = $_SERVER;
		$_SERVER[ 'HTTP_HOST' ] = PasskeyFixtureLoader::requestHost();
		$_SERVER[ 'HTTPS' ] = 'on';
		$_SERVER[ 'REQUEST_METHOD' ] = 'POST';
		$_SERVER[ 'REQUEST_URI' ] = '/wp-login.php';
	}

	protected function tearDown() :void {
		$_SERVER = $this->serverSnapshot;
		parent::tearDown();
	}

	public function test_registration_replay_succeeds() :void {
		$result = $this->makeAdapter()->verifyRegistration(
			PasskeyFixtureLoader::registrationResponse(),
			PasskeyFixtureLoader::registrationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);

		$this->assertSame( PasskeyFixtureLoader::registrationCredentialId(), $result[ 'publicKeyCredentialId' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::registrationExpectedCounter(), (int)( $result[ 'counter' ] ?? 0 ) );
	}

	public function test_registration_replay_rejects_wrong_origin() :void {
		$this->expectException( AssertionFailedException::class );

		$this->makeAdapter()->verifyRegistration(
			PasskeyFixtureLoader::registrationResponse( [], [
				'origin' => 'https://evil.example',
			] ),
			PasskeyFixtureLoader::registrationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);
	}

	public function test_registration_replay_rejects_malformed_payload() :void {
		$this->assertMalformedPayloadFailure( function () :void {
			$this->makeAdapter()->verifyRegistration(
				'{"id":"broken"}',
				PasskeyFixtureLoader::registrationOptions(),
				$this->makeContext(),
				new InMemoryPasskeySourcesHandler()
			);
		} );
	}

	public function test_registration_refuses_an_out_of_bounds_rsa_key_that_the_library_accepts() :void {
		$result = $this->makeAdapter()->verifyRegistration(
			$this->registrationResponseWithKey( $this->rsaCoseKey( self::ALG_RS256, $this->modulus( 256 ), self::EXPONENT_65537 ) ),
			PasskeyFixtureLoader::registrationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);
		$this->assertSame( PasskeyFixtureLoader::registrationCredentialId(), $result[ 'publicKeyCredentialId' ] ?? '' );

		$this->expectException( \InvalidArgumentException::class );
		$this->makeAdapter()->verifyRegistration(
			$this->registrationResponseWithKey( $this->rsaCoseKey( self::ALG_RS256, $this->modulus( 1024 ), self::EXPONENT_65537 ) ),
			PasskeyFixtureLoader::registrationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);
	}

	/**
	 * Refused before the library's own credential lookup: an in-bounds key gets as far as the library, which looks
	 * the passkey up again before failing on the signature.
	 */
	public function test_authentication_refuses_an_out_of_bounds_stored_key_before_the_library_verifies_it() :void {
		$lookups = function ( int $modulusBytes ) :int {
			$repo = new InMemoryPasskeySourcesHandler( [
				$this->fixtureSourceWithKey( $this->rsaCoseKey( self::ALG_RS256, $this->modulus( $modulusBytes ), self::EXPONENT_65537 ) ),
			] );
			try {
				$this->makeAdapter()->verifyAuthentication(
					PasskeyFixtureLoader::authenticationResponse(),
					PasskeyFixtureLoader::authenticationOptions(),
					$this->makeContext(),
					$repo
				);
				$this->fail( 'Expected verification with a key that did not sign the response to fail.' );
			}
			catch ( \InvalidArgumentException $e ) {
			}
			return $repo->lookups;
		};

		$this->assertSame( 2, $lookups( 256 ) );
		$this->assertSame( 1, $lookups( 1024 ) );
	}

	public function test_authentication_replay_succeeds() :void {
		$result = $this->makeAdapter()->verifyAuthentication(
			PasskeyFixtureLoader::authenticationResponse(),
			PasskeyFixtureLoader::authenticationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler( [ $this->legacySource() ] )
		);

		$this->assertSame( PasskeyFixtureLoader::credentialId(), $result[ 'publicKeyCredentialId' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::authenticationExpectedCounter(), (int)( $result[ 'counter' ] ?? 0 ) );
	}

	public function test_authentication_replay_rejects_wrong_origin() :void {
		$this->expectException( AssertionFailedException::class );

		$this->makeAdapter()->verifyAuthentication(
			PasskeyFixtureLoader::authenticationResponse( [], [
				'origin' => 'https://evil.example',
			] ),
			PasskeyFixtureLoader::authenticationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler( [ $this->legacySource() ] )
		);
	}

	public function test_authentication_replay_rejects_wrong_challenge() :void {
		$this->expectException( AssertionFailedException::class );

		$options = PasskeyFixtureLoader::authenticationOptions();
		$options[ 'challenge' ] = Base64Url::encode( \random_bytes( 32 ) );

		$this->makeAdapter()->verifyAuthentication(
			PasskeyFixtureLoader::authenticationResponse(),
			$options,
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler( [ $this->legacySource() ] )
		);
	}

	public function test_authentication_replay_rejects_malformed_payload() :void {
		$this->assertMalformedPayloadFailure( function () :void {
			$this->makeAdapter()->verifyAuthentication(
				'{"id":"broken"}',
				PasskeyFixtureLoader::authenticationOptions(),
				$this->makeContext(),
				new InMemoryPasskeySourcesHandler( [ $this->legacySource() ] )
			);
		} );
	}

	/**
	 * The fixture registration with its credential public key replaced. The fixture uses attestation "none" and its
	 * authenticator data ends with the key, so the result is a registration the library accepts as genuine.
	 */
	private function registrationResponseWithKey( string $coseKey ) :string {
		$response = PasskeyFixtureLoader::load()[ 'registration' ][ 'response' ];
		$decoder = new Decoder( new TagObjectManager(), new OtherObjectManager() );
		$attestation = $decoder->decode( new StringStream( Base64Url::decode( $response[ 'response' ][ 'attestationObject' ] ) ) )
							   ->getNormalizedData();

		$authData = $attestation[ 'authData' ];
		$credentialIdLength = \unpack( 'n', \substr( $authData, 53, 2 ) )[ 1 ];

		$rebuilt = MapObject::create()
							->add( TextStringObject::create( 'fmt' ), TextStringObject::create( $attestation[ 'fmt' ] ) )
							->add( TextStringObject::create( 'attStmt' ), MapObject::create() )
							->add( TextStringObject::create( 'authData' ), ByteStringObject::create(
								\substr( $authData, 0, 55 + $credentialIdLength ).$coseKey
							) );

		return PasskeyFixtureLoader::registrationResponse( [
			'response' => [ 'attestationObject' => Base64Url::encode( (string)$rebuilt ) ],
		] );
	}

	private function makeAdapter() :WebauthnLibAdapter {
		return new WebauthnLibAdapter();
	}

	private function makeContext() :PasskeyAdapterContext {
		$registrationOptions = PasskeyFixtureLoader::registrationOptions();

		return new PasskeyAdapterContext(
			PasskeyFixtureLoader::requestHost(),
			(string)( $registrationOptions[ 'rp' ][ 'name' ] ?? 'Fixture RP' ),
			(string)( $registrationOptions[ 'user' ][ 'name' ] ?? 'fixture-user' ),
			PasskeyFixtureLoader::userHandleRaw(),
			(string)( $registrationOptions[ 'user' ][ 'displayName' ] ?? 'Fixture User' ),
			''
		);
	}

	private function legacySource() :PublicKeyCredentialSource {
		return PublicKeyCredentialSource::createFromArray( PasskeyFixtureLoader::legacyRecord() );
	}

	private function assertMalformedPayloadFailure( callable $callback ) :void {
		try {
			$callback();
			$this->fail( 'Expected malformed payload verification to throw.' );
		}
		catch ( \Throwable $e ) {
			$this->assertTrue(
				$e instanceof AssertionFailedException || $e instanceof \InvalidArgumentException,
				'Unexpected exception type: '.\get_class( $e )
			);
		}
	}
}

class InMemoryPasskeySourcesHandler extends PasskeySourcesHandler {

	/**
	 * @var PublicKeyCredentialSource[]
	 */
	private array $sources;

	public int $lookups = 0;

	public function __construct( array $sources = [] ) {
		$this->sources = $sources;
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?PublicKeyCredentialSource {
		$this->lookups++;
		foreach ( $this->sources as $source ) {
			if ( $source->getPublicKeyCredentialId() === $publicKeyCredentialId ) {
				return $source;
			}
		}

		return null;
	}

	public function findAllForUserEntity( PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity ) :array {
		return $this->sources;
	}

	public function getExcludedSourcesFromAllUsers() :array {
		return $this->sources;
	}

	public function saveCredentialSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :void {
		foreach ( $this->sources as $idx => $source ) {
			if ( $source->getPublicKeyCredentialId() === $publicKeyCredentialSource->getPublicKeyCredentialId() ) {
				$this->sources[ $idx ] = $publicKeyCredentialSource;
				return;
			}
		}

		$this->sources[] = $publicKeyCredentialSource;
	}

	public function updateSource( PublicKeyCredentialSource $publicKeyCredentialSource, array $data = [] ) :void {
		$this->saveCredentialSource( $publicKeyCredentialSource );
	}
}

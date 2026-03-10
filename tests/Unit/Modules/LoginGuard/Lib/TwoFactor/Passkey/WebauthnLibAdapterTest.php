<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use Assert\AssertionFailedException;
use Base64Url\Base64Url;
use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey\{
	PasskeyAdapterContext,
	WebauthnLibAdapter
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\PasskeyFixtureLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Webauthn\{
	PublicKeyCredentialSource,
	PublicKeyCredentialUserEntity
};

class WebauthnLibAdapterTest extends BaseUnitTest {

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

	public function __construct( array $sources = [] ) {
		$this->sources = $sources;
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?PublicKeyCredentialSource {
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey\{
	PasskeyAdapterContext,
	WebauthnLibAdapter
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\{
	PasskeyBase64Url,
	PasskeyCredentialDataNormalizer,
	PasskeySourcesHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\PasskeyFixtureLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\{
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

	public function test_registration_options_only_advertise_algorithms_supported_by_v5_verifier() :void {
		$adapter = $this->makeAdapter();
		$expectedAlgorithms = [ -257, -259, -37, -39, -7, -36 ];
		if ( \function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			$expectedAlgorithms[] = -8;
		}

		$options = $adapter->startRegistration(
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);
		$this->assertArrayHasKey( 'pubKeyCredParams', $options );
		$this->assertIsArray( $options[ 'pubKeyCredParams' ] );
		$algorithms = \array_column( $options[ 'pubKeyCredParams' ], 'alg' );

		$this->assertSame( $expectedAlgorithms, $algorithms );
		$this->assertSame( $algorithms, $adapter->signatureAlgorithmIds() );
	}

	public function test_registration_options_omit_ed25519_when_sodium_is_unavailable() :void {
		$options = $this->makeAdapterWithoutEd25519()->startRegistration(
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);

		$this->assertSame(
			[ -257, -259, -37, -39, -7, -36 ],
			\array_column( $options[ 'pubKeyCredParams' ], 'alg' )
		);
	}

	public function test_allowed_origin_prefers_context_origin() :void {
		$_SERVER[ 'HTTP_HOST' ] = 'wrong.example';
		$_SERVER[ 'HTTPS' ] = 'off';

		$origin = 'https://'.PasskeyFixtureLoader::requestHost().':8443';

		$this->assertSame( [ $origin ], $this->makeAdapter()->allowedOriginsFor( $this->makeContext( $origin ) ) );
	}

	public function test_allowed_origin_fallback_preserves_request_port() :void {
		$_SERVER[ 'HTTP_HOST' ] = PasskeyFixtureLoader::requestHost().':8443';

		$this->assertSame(
			[ 'https://'.PasskeyFixtureLoader::requestHost().':8443' ],
			$this->makeAdapter()->allowedOriginsFor( $this->makeContext() )
		);
	}

	public function test_registration_replay_succeeds() :void {
		$result = $this->makeAdapter()->verifyRegistration(
			PasskeyFixtureLoader::registrationResponse(),
			PasskeyFixtureLoader::registrationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler()
		);

		$this->assertCredentialResult(
			$result,
			PasskeyFixtureLoader::registrationCredentialId(),
			PasskeyFixtureLoader::registrationExpectedCounter()
		);
	}

	public function test_registration_replay_rejects_wrong_origin() :void {
		$this->expectException( AuthenticatorResponseVerificationException::class );

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
			new InMemoryPasskeySourcesHandler( [ $this->legacyCredentialRecord() ] )
		);

		$this->assertCredentialResult(
			$result,
			PasskeyFixtureLoader::credentialId(),
			PasskeyFixtureLoader::authenticationExpectedCounter()
		);
	}

	public function test_authentication_replay_rejects_wrong_origin() :void {
		$this->expectException( AuthenticatorResponseVerificationException::class );

		$this->makeAdapter()->verifyAuthentication(
			PasskeyFixtureLoader::authenticationResponse( [], [
				'origin' => 'https://evil.example',
			] ),
			PasskeyFixtureLoader::authenticationOptions(),
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler( [ $this->legacyCredentialRecord() ] )
		);
	}

	public function test_authentication_replay_rejects_wrong_challenge() :void {
		$this->expectException( AuthenticatorResponseVerificationException::class );

		$options = PasskeyFixtureLoader::authenticationOptions();
		$options[ 'challenge' ] = PasskeyBase64Url::encode( \random_bytes( 32 ) );

		$this->makeAdapter()->verifyAuthentication(
			PasskeyFixtureLoader::authenticationResponse(),
			$options,
			$this->makeContext(),
			new InMemoryPasskeySourcesHandler( [ $this->legacyCredentialRecord() ] )
		);
	}

	public function test_authentication_replay_rejects_malformed_payload() :void {
		$this->assertMalformedPayloadFailure( function () :void {
			$this->makeAdapter()->verifyAuthentication(
				'{"id":"broken"}',
				PasskeyFixtureLoader::authenticationOptions(),
				$this->makeContext(),
				new InMemoryPasskeySourcesHandler( [ $this->legacyCredentialRecord() ] )
			);
		} );
	}

	private function makeAdapter() :ExposedWebauthnLibAdapter {
		return new ExposedWebauthnLibAdapter();
	}

	private function makeAdapterWithoutEd25519() :WebauthnLibAdapterWithoutEd25519 {
		return new WebauthnLibAdapterWithoutEd25519();
	}

	private function makeContext( ?string $origin = null ) :PasskeyAdapterContext {
		$registrationOptions = PasskeyFixtureLoader::registrationOptions();

		return new PasskeyAdapterContext(
			PasskeyFixtureLoader::requestHost(),
			(string)$registrationOptions[ 'rp' ][ 'name' ],
			(string)$registrationOptions[ 'user' ][ 'name' ],
			PasskeyFixtureLoader::userHandleRaw(),
			(string)$registrationOptions[ 'user' ][ 'displayName' ],
			'',
			$origin
		);
	}

	private function legacyCredentialRecord() :CredentialRecord {
		$credentialRecord = ( new WebauthnSerializerFactory( new AttestationStatementSupportManager() ) )
			->create()
			->denormalize(
				( new PasskeyCredentialDataNormalizer() )->normalizeForWebauthn( PasskeyFixtureLoader::legacyRecord() ),
				CredentialRecord::class
			);

		if ( !$credentialRecord instanceof CredentialRecord ) {
			throw new \UnexpectedValueException( 'Invalid fixture credential record.' );
		}

		return $credentialRecord;
	}

	private function assertMalformedPayloadFailure( callable $callback ) :void {
		try {
			$callback();
			$this->fail( 'Expected malformed payload verification to throw.' );
		}
		catch ( \Throwable $e ) {
			$this->assertTrue(
				$e instanceof AuthenticatorResponseVerificationException
				|| $e instanceof \InvalidArgumentException
				|| $e instanceof \RangeException,
				'Unexpected exception type: '.\get_class( $e )
			);
		}
	}

	private function assertCredentialResult( array $result, string $credentialId, int $counter ) :void {
		$this->assertArrayHasKey( 'publicKeyCredentialId', $result );
		$this->assertArrayHasKey( 'counter', $result );
		$this->assertSame( $credentialId, $result[ 'publicKeyCredentialId' ] );
		$this->assertIsInt( $result[ 'counter' ] );
		$this->assertSame( $counter, $result[ 'counter' ] );
	}
}

class ExposedWebauthnLibAdapter extends WebauthnLibAdapter {

	/**
	 * @return int[]
	 */
	public function signatureAlgorithmIds() :array {
		return \array_map(
			static fn( $algorithm ) :int => $algorithm::identifier(),
			$this->signatureAlgorithms()
		);
	}

	/**
	 * @return string[]
	 */
	public function allowedOriginsFor( PasskeyAdapterContext $context ) :array {
		return $this->allowedOrigins( $context );
	}
}

class WebauthnLibAdapterWithoutEd25519 extends ExposedWebauthnLibAdapter {

	protected function isEd25519Available() :bool {
		return false;
	}
}

class InMemoryPasskeySourcesHandler extends PasskeySourcesHandler {

	/**
	 * @var CredentialRecord[]
	 */
	private array $credentialRecords;

	public function __construct( array $credentialRecords = [] ) {
		$this->credentialRecords = $credentialRecords;
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?CredentialRecord {
		foreach ( $this->credentialRecords as $credentialRecord ) {
			if ( $credentialRecord->publicKeyCredentialId === $publicKeyCredentialId ) {
				return $credentialRecord;
			}
		}

		return null;
	}

	public function findAllForUserEntity( PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity ) :array {
		return $this->credentialRecords;
	}

	public function getExcludedCredentialRecordsForCurrentUser() :array {
		return $this->credentialRecords;
	}

	public function saveCredentialRecord( CredentialRecord $credentialRecord ) :void {
		foreach ( $this->credentialRecords as $idx => $source ) {
			if ( $source->publicKeyCredentialId === $credentialRecord->publicKeyCredentialId ) {
				$this->credentialRecords[ $idx ] = $credentialRecord;
				return;
			}
		}

		$this->credentialRecords[] = $credentialRecord;
	}

	public function updateCredentialRecord( CredentialRecord $credentialRecord, array $data = [] ) :void {
		$this->saveCredentialRecord( $credentialRecord );
	}
}

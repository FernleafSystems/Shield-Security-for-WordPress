<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use Base64Url\Base64Url;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Webauthn\{
	AttestationStatement\AttestationObjectLoader,
	AttestationStatement\AttestationStatementSupportManager,
	AttestationStatement\NoneAttestationStatementSupport,
	AuthenticationExtensions\ExtensionOutputCheckerHandler,
	AuthenticatorAttestationResponse,
	AuthenticatorAttestationResponseValidator,
	AuthenticatorSelectionCriteria,
	PublicKeyCredentialCreationOptions,
	PublicKeyCredentialLoader,
	PublicKeyCredentialRequestOptions,
	PublicKeyCredentialRpEntity,
	PublicKeyCredentialSource,
	PublicKeyCredentialUserEntity,
	Server,
	TokenBinding\IgnoreTokenBindingHandler
};

class WebauthnLibAdapter implements PasskeyAdapterInterface {

	public function startRegistration( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array {
		$options = $this->getServer( $context, $sourceRepo )->generatePublicKeyCredentialCreationOptions(
			$this->getUserEntity( $context ),
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $sourceRepo->getExcludedSourcesFromAllUsers() ),
			new AuthenticatorSelectionCriteria(
				AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
				false,
				AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED
			)
		);
		$options->setTimeout( 60000 );

		return $options->jsonSerialize();
	}

	public function startAuthentication( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array {
		$options = $this->getServer( $context, $sourceRepo )->generatePublicKeyCredentialRequestOptions(
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			\array_map( function ( PublicKeyCredentialSource $credential ) {
				return $credential->getPublicKeyCredentialDescriptor();
			}, $sourceRepo->findAllForUserEntity( $this->getUserEntity( $context ) ) )
		);
		$options->setTimeout( 60000 );

		return $options->jsonSerialize();
	}

	public function verifyRegistration(
		string $rawResponseJson,
		array $registrationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array {
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add( new NoneAttestationStatementSupport() );
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader(
			new AttestationObjectLoader( $attestationStatementSupportManager )
		);

		$authenticatorAttestationResponse = $publicKeyCredentialLoader
			->load( $rawResponseJson )
			->getResponse();
		if ( !$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse ) {
			throw new \Exception( 'invalid AuthenticatorAttestationResponse response' );
		}

		$validator = new AuthenticatorAttestationResponseValidator(
			$attestationStatementSupportManager,
			$sourceRepo,
			new IgnoreTokenBindingHandler(),
			new ExtensionOutputCheckerHandler()
		);

		$source = $validator->check(
			$authenticatorAttestationResponse,
			$this->creationOptionsFromArray( $registrationOptions ),
			$this->createServerRequest()
		);

		// Never store a key that sign-in would refuse.
		$this->assertKeyAccepted( $source );

		return $source->jsonSerialize();
	}

	public function verifyAuthentication(
		string $rawResponseJson,
		array $authenticationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array {
		$this->assertStoredKeyAccepted( $rawResponseJson, $sourceRepo );

		return $this->getServer( $context, $sourceRepo )->loadAndCheckAssertionResponse(
			$rawResponseJson,
			$this->requestOptionsFromArray( $authenticationOptions ),
			$this->getUserEntity( $context ),
			$this->createServerRequest()
		)->jsonSerialize();
	}

	/**
	 * Refuses the stored key before the library verifies a signature with it. The library looks the passkey up by
	 * rawId, so the same passkey is checked here. A response without a usable rawId is left for the library to reject.
	 */
	private function assertStoredKeyAccepted( string $rawResponseJson, PasskeySourcesHandler $sourceRepo ) :void {
		$response = \json_decode( $rawResponseJson, true );
		$rawId = \is_array( $response ) && \is_string( $response[ 'rawId' ] ?? null ) ? Base64Url::decode( $response[ 'rawId' ] ) : '';
		$source = $rawId === '' ? null : $sourceRepo->findOneByCredentialId( $rawId );
		if ( $source !== null ) {
			$this->assertKeyAccepted( $source );
		}
	}

	/**
	 * Temporary hardening for the cose-lib 3.x RSA advisories, see CredentialPublicKeyPolicy. Remove with it.
	 * @throws \InvalidArgumentException
	 */
	private function assertKeyAccepted( PublicKeyCredentialSource $source ) :void {
		if ( !( new CredentialPublicKeyPolicy() )->accepts( $source ) ) {
			throw new \InvalidArgumentException( 'This passkey uses a key that is not accepted.' );
		}
	}

	private function creationOptionsFromArray( array $options ) :PublicKeyCredentialCreationOptions {
		$creationOptions = PublicKeyCredentialCreationOptions::createFromArray( $options );
		if ( !$creationOptions instanceof PublicKeyCredentialCreationOptions ) {
			throw new \InvalidArgumentException( 'Invalid PublicKeyCredentialCreationOptions payload.' );
		}
		return $creationOptions;
	}

	private function requestOptionsFromArray( array $options ) :PublicKeyCredentialRequestOptions {
		$requestOptions = PublicKeyCredentialRequestOptions::createFromArray( $options );
		if ( !$requestOptions instanceof PublicKeyCredentialRequestOptions ) {
			throw new \InvalidArgumentException( 'Invalid PublicKeyCredentialRequestOptions payload.' );
		}
		return $requestOptions;
	}

	private function createServerRequest() :\Psr\Http\Message\ServerRequestInterface {
		$psr17Factory = new Psr17Factory();
		$creator = new ServerRequestCreator(
			$psr17Factory,
			$psr17Factory,
			$psr17Factory,
			$psr17Factory
		);

		return $creator->fromGlobals();
	}

	private function getServer( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :Server {
		return new Server(
			new PublicKeyCredentialRpEntity(
				$context->relyingPartyName,
				$context->relyingPartyId,
				null
			),
			$sourceRepo,
			null
		);
	}

	private function getUserEntity( PasskeyAdapterContext $context ) :PublicKeyCredentialUserEntity {
		return new PublicKeyCredentialUserEntity(
			$context->userName,
			$context->userHandle,
			$context->userDisplayName,
			$context->userAvatarUrl
		);
	}
}

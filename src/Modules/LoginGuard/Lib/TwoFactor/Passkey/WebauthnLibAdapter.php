<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

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

		return $validator->check(
			$authenticatorAttestationResponse,
			PublicKeyCredentialCreationOptions::createFromArray( $registrationOptions ),
			$this->createServerRequest()
		)->jsonSerialize();
	}

	public function verifyAuthentication(
		string $rawResponseJson,
		array $authenticationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array {
		return $this->getServer( $context, $sourceRepo )->loadAndCheckAssertionResponse(
			$rawResponseJson,
			PublicKeyCredentialRequestOptions::createFromArray( $authenticationOptions ),
			$this->getUserEntity( $context ),
			$this->createServerRequest()
		)->jsonSerialize();
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

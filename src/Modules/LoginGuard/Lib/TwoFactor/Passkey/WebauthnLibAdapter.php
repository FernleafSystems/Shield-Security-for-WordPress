<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use Cose\Algorithm\{
	Algorithm as CoseAlgorithm,
	Manager as CoseAlgorithmManager
};
use Cose\Algorithm\Signature\ECDSA\{
	ES256,
	ES512
};
use Cose\Algorithm\Signature\EdDSA\Ed25519;
use Cose\Algorithm\Signature\RSA\{
	PS256,
	PS512,
	RS256,
	RS512
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\{
	PasskeyBase64Url,
	PasskeySourcesHandler
};
use Symfony\Component\Serializer\Normalizer\{
	DenormalizerInterface,
	NormalizerInterface
};
use Webauthn\{
	AttestationStatement\AttestationStatementSupportManager,
	AuthenticatorAssertionResponse,
	AuthenticatorAssertionResponseValidator,
	AuthenticatorAttestationResponse,
	AuthenticatorAttestationResponseValidator,
	AuthenticatorSelectionCriteria,
	CeremonyStep\CeremonyStepManagerFactory,
	CredentialRecord,
	Denormalizer\WebauthnSerializerFactory,
	PublicKeyCredential,
	PublicKeyCredentialCreationOptions,
	PublicKeyCredentialDescriptor,
	PublicKeyCredentialParameters,
	PublicKeyCredentialRequestOptions,
	PublicKeyCredentialRpEntity,
	PublicKeyCredentialUserEntity
};

class WebauthnLibAdapter implements PasskeyAdapterInterface {

	private const TIMEOUT_MS = 60000;

	public function startRegistration( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array {
		return [
			'rp'                     => [
				'name' => $context->relyingPartyName,
				'id'   => $context->relyingPartyId,
			],
			'user'                   => [
				'name'        => $context->userName,
				'id'          => PasskeyBase64Url::encode( $context->userHandle ),
				'displayName' => $context->userDisplayName,
			],
			'challenge'              => PasskeyBase64Url::encode( \random_bytes( 32 ) ),
			'pubKeyCredParams'       => $this->credentialParametersPayload(),
			'timeout'                => self::TIMEOUT_MS,
			'excludeCredentials'     => \array_map(
				fn( CredentialRecord $credential ) => $this->credentialDescriptorPayload(
					$credential->getPublicKeyCredentialDescriptor()
				),
				$sourceRepo->getExcludedCredentialRecordsForCurrentUser()
			),
			'authenticatorSelection' => [
				'requireResidentKey' => false,
				'userVerification'   => AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			],
			'attestation'            => PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
		];
	}

	public function startAuthentication( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array {
		return [
			'challenge'        => PasskeyBase64Url::encode( \random_bytes( 32 ) ),
			'rpId'             => $context->relyingPartyId,
			'userVerification' => PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			'allowCredentials' => \array_map(
				fn( CredentialRecord $credential ) => $this->credentialDescriptorPayload(
					$credential->getPublicKeyCredentialDescriptor()
				),
				$sourceRepo->findAllForUserEntity( $this->getUserEntity( $context ) )
			),
			'timeout'          => self::TIMEOUT_MS,
		];
	}

	public function verifyRegistration(
		string $rawResponseJson,
		array $registrationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array {
		$credential = $this->credentialFromJson( $rawResponseJson );
		$response = $credential->response;
		if ( !$response instanceof AuthenticatorAttestationResponse ) {
			throw new \InvalidArgumentException( 'Invalid AuthenticatorAttestationResponse response.' );
		}

		$credentialRecord = ( new AuthenticatorAttestationResponseValidator(
			$this->ceremonyStepManagerFactory( $context )->creationCeremony()
		) )->check(
			$response,
			$this->creationOptionsFromArray( $registrationOptions ),
			$context->relyingPartyId
		);

		return $this->normalizeCredentialRecord( $credentialRecord );
	}

	public function verifyAuthentication(
		string $rawResponseJson,
		array $authenticationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array {
		$credential = $this->credentialFromJson( $rawResponseJson );
		$response = $credential->response;
		if ( !$response instanceof AuthenticatorAssertionResponse ) {
			throw new \InvalidArgumentException( 'Invalid AuthenticatorAssertionResponse response.' );
		}

		$credentialRecord = $sourceRepo->findOneByCredentialId( $credential->rawId );
		if ( $credentialRecord === null ) {
			throw new \InvalidArgumentException( 'Passkey credential record not found.' );
		}

		$credentialRecord = ( new AuthenticatorAssertionResponseValidator(
			$this->ceremonyStepManagerFactory( $context )->requestCeremony()
		) )->check(
			$credentialRecord,
			$response,
			$this->requestOptionsFromArray( $authenticationOptions ),
			$context->relyingPartyId,
			$context->userHandle
		);

		return $this->normalizeCredentialRecord( $credentialRecord );
	}

	private function credentialFromJson( string $rawResponseJson ) :PublicKeyCredential {
		$data = \json_decode( $rawResponseJson, true );
		if ( !\is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Invalid passkey response JSON.' );
		}

		$credential = $this->serializer()->denormalize( $data, PublicKeyCredential::class );
		if ( !$credential instanceof PublicKeyCredential ) {
			throw new \InvalidArgumentException( 'Invalid PublicKeyCredential payload.' );
		}

		return $credential;
	}

	private function creationOptionsFromArray( array $options ) :PublicKeyCredentialCreationOptions {
		$rp = $this->requiredArray( $options, 'rp' );
		$user = $this->requiredArray( $options, 'user' );
		$authenticatorSelection = $this->optionalArray( $options, 'authenticatorSelection' );
		$userVerification = $this->optionalString(
			$authenticatorSelection,
			'userVerification'
		) ?? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED;
		$residentKey = $this->optionalBool( $authenticatorSelection, 'requireResidentKey' ) === true
			? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
			: ( $this->optionalString( $authenticatorSelection, 'residentKey' )
				?? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_NO_PREFERENCE );

		return PublicKeyCredentialCreationOptions::create(
			PublicKeyCredentialRpEntity::create(
				$this->requiredString( $rp, 'name' ),
				$this->requiredString( $rp, 'id' )
			),
			PublicKeyCredentialUserEntity::create(
				$this->requiredString( $user, 'name' ),
				PasskeyBase64Url::decode( $this->requiredString( $user, 'id' ) ),
				$this->requiredString( $user, 'displayName' )
			),
			PasskeyBase64Url::decode( $this->requiredString( $options, 'challenge' ) ),
			\array_map(
				fn( array $parameter ) => $this->credentialParameterFromArray( $parameter ),
				$this->requiredArrayList( $options, 'pubKeyCredParams' )
			),
			AuthenticatorSelectionCriteria::create(
				$this->optionalString( $authenticatorSelection, 'authenticatorAttachment' ),
				$userVerification,
				$residentKey
			),
			$this->optionalString( $options, 'attestation' ),
			\array_map(
				fn( array $descriptor ) => $this->credentialDescriptorFromArray( $descriptor ),
				$this->optionalArrayList( $options, 'excludeCredentials' )
			),
			$this->optionalInt( $options, 'timeout' )
		);
	}

	private function requestOptionsFromArray( array $options ) :PublicKeyCredentialRequestOptions {
		return PublicKeyCredentialRequestOptions::create(
			PasskeyBase64Url::decode( $this->requiredString( $options, 'challenge' ) ),
			$this->requiredString( $options, 'rpId' ),
			\array_map(
				fn( array $descriptor ) => $this->credentialDescriptorFromArray( $descriptor ),
				$this->requiredArrayList( $options, 'allowCredentials' )
			),
			$this->requiredString( $options, 'userVerification' ),
			$this->optionalInt( $options, 'timeout' )
		);
	}

	private function ceremonyStepManagerFactory( PasskeyAdapterContext $context ) :CeremonyStepManagerFactory {
		$factory = new CeremonyStepManagerFactory();
		$factory->setAlgorithmManager( $this->algorithmManager() );
		$factory->setAllowedOrigins( $this->allowedOrigins( $context ) );
		return $factory;
	}

	private function algorithmManager() :CoseAlgorithmManager {
		return CoseAlgorithmManager::create()->add( ...$this->signatureAlgorithms() );
	}

	private function normalizeCredentialRecord( CredentialRecord $credentialRecord ) :array {
		return $this->normalizeToArray( $credentialRecord );
	}

	private function normalizeToArray( object $object ) :array {
		$normalized = $this->serializer()->normalize( $object );
		if ( !\is_array( $normalized ) ) {
			throw new \UnexpectedValueException( sprintf(
				'Expected WebAuthn normalizer to return an array, got %s.',
				\get_debug_type( $normalized )
			) );
		}
		return $normalized;
	}

	private function getUserEntity( PasskeyAdapterContext $context ) :PublicKeyCredentialUserEntity {
		return PublicKeyCredentialUserEntity::create(
			$context->userName,
			$context->userHandle,
			$context->userDisplayName,
			$context->userAvatarUrl
		);
	}

	/**
	 * @return PublicKeyCredentialParameters[]
	 */
	private function credentialParameters() :array {
		return \array_map(
			static fn( CoseAlgorithm $algorithm ) => PublicKeyCredentialParameters::create(
				PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
				$algorithm::identifier()
			),
			$this->signatureAlgorithms()
		);
	}

	/**
	 * @return CoseAlgorithm[]
	 */
	protected function signatureAlgorithms() :array {
		$algorithms = [
			RS256::create(),
			RS512::create(),
			PS256::create(),
			PS512::create(),
			ES256::create(),
			ES512::create(),
		];

		if ( $this->isEd25519Available() ) {
			$algorithms[] = Ed25519::create();
		}

		return $algorithms;
	}

	protected function isEd25519Available() :bool {
		return \function_exists( 'sodium_crypto_sign_verify_detached' );
	}

	protected function allowedOrigins( PasskeyAdapterContext $context ) :array {
		return [
			$context->relyingPartyOrigin ?? $this->currentRequestOrigin( $context->relyingPartyId ),
		];
	}

	private function credentialParameterFromArray( array $parameter ) :PublicKeyCredentialParameters {
		return PublicKeyCredentialParameters::create(
			$this->requiredString( $parameter, 'type' ),
			$this->requiredInt( $parameter, 'alg' )
		);
	}

	private function credentialDescriptorFromArray( array $descriptor ) :PublicKeyCredentialDescriptor {
		return PublicKeyCredentialDescriptor::create(
			$this->requiredString( $descriptor, 'type' ),
			PasskeyBase64Url::decode( $this->requiredString( $descriptor, 'id' ) ),
			$this->optionalStringList( $descriptor, 'transports' )
		);
	}

	private function credentialDescriptorPayload( PublicKeyCredentialDescriptor $descriptor ) :array {
		$payload = [
			'type' => $descriptor->type,
			'id'   => PasskeyBase64Url::encode( $descriptor->id ),
		];

		if ( !empty( $descriptor->transports ) ) {
			$payload[ 'transports' ] = $descriptor->transports;
		}

		return $payload;
	}

	private function credentialParametersPayload() :array {
		return \array_map(
			static fn( PublicKeyCredentialParameters $parameter ) => [
				'type' => $parameter->type,
				'alg'  => $parameter->alg,
			],
			$this->credentialParameters()
		);
	}

	private function currentRequestScheme() :string {
		$https = (string)( $_SERVER[ 'HTTPS' ] ?? '' );
		return $https !== '' && \strtolower( $https ) !== 'off' ? 'https' : 'http';
	}

	private function currentRequestOrigin( string $fallbackHost ) :string {
		$host = (string)( $_SERVER[ 'HTTP_HOST' ] ?? '' );
		return sprintf( '%s://%s', $this->currentRequestScheme(), $host === '' ? $fallbackHost : $host );
	}

	private function requiredString( array $source, string $key ) :string {
		if ( !\array_key_exists( $key, $source ) || !\is_string( $source[ $key ] ) || $source[ $key ] === '' ) {
			throw new \InvalidArgumentException( sprintf( 'Missing passkey option string: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function optionalString( array $source, string $key ) :?string {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return null;
		}

		if ( !\is_string( $source[ $key ] ) || $source[ $key ] === '' ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid passkey option string: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function requiredInt( array $source, string $key ) :int {
		if ( !\array_key_exists( $key, $source ) || !\is_int( $source[ $key ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Missing passkey option integer: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function optionalInt( array $source, string $key ) :?int {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return null;
		}

		if ( !\is_int( $source[ $key ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid passkey option integer: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function optionalBool( array $source, string $key ) :?bool {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return null;
		}

		if ( !\is_bool( $source[ $key ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid passkey option boolean: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function requiredArray( array $source, string $key ) :array {
		if ( !\array_key_exists( $key, $source ) || !\is_array( $source[ $key ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Missing passkey option array: %s.', $key ) );
		}

		return $source[ $key ];
	}

	private function optionalArray( array $source, string $key ) :array {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return [];
		}

		return $this->requiredArray( $source, $key );
	}

	/**
	 * @return list<array>
	 */
	private function requiredArrayList( array $source, string $key ) :array {
		$values = [];
		foreach ( $this->requiredArray( $source, $key ) as $value ) {
			if ( !\is_array( $value ) ) {
				throw new \InvalidArgumentException( sprintf( 'Invalid passkey option list item: %s.', $key ) );
			}
			$values[] = $value;
		}

		return $values;
	}

	/**
	 * @return list<array>
	 */
	private function optionalArrayList( array $source, string $key ) :array {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return [];
		}

		return $this->requiredArrayList( $source, $key );
	}

	/**
	 * @return list<string>
	 */
	private function optionalStringList( array $source, string $key ) :array {
		if ( !\array_key_exists( $key, $source ) || $source[ $key ] === null ) {
			return [];
		}

		$values = [];
		foreach ( $this->requiredArray( $source, $key ) as $value ) {
			if ( !\is_string( $value ) || $value === '' ) {
				throw new \InvalidArgumentException( sprintf( 'Invalid passkey option string list item: %s.', $key ) );
			}
			$values[] = $value;
		}

		return $values;
	}

	private function serializer() :NormalizerInterface&DenormalizerInterface {
		$serializer = ( new WebauthnSerializerFactory( new AttestationStatementSupportManager() ) )->create();
		if ( !$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface ) {
			throw new \UnexpectedValueException( 'Expected WebAuthn serializer to support normalization and denormalization.' );
		}

		return $serializer;
	}
}

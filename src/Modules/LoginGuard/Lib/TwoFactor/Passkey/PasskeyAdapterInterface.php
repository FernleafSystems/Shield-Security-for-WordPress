<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;

/**
 * @phpstan-type PasskeyCredentialDescriptorPayload array{type:string,id:string,transports?:list<string>}
 * @phpstan-type PasskeyRegistrationOptionsPayload array{
 *   rp:array{name:string,id:string},
 *   user:array{name:string,id:string,displayName:string},
 *   challenge:string,
 *   pubKeyCredParams:list<array{type:string,alg:int}>,
 *   timeout:int,
 *   excludeCredentials:list<PasskeyCredentialDescriptorPayload>,
 *   authenticatorSelection:array{requireResidentKey:bool,userVerification:string},
 *   attestation:string
 * }
 * @phpstan-type PasskeyAuthenticationOptionsPayload array{
 *   challenge:string,
 *   rpId:string,
 *   userVerification:string,
 *   allowCredentials:list<PasskeyCredentialDescriptorPayload>,
 *   timeout:int
 * }
 * @phpstan-type PasskeyCredentialRecordPayload array{publicKeyCredentialId:string,counter:int,trustPath?:array<string,mixed>}
 */
interface PasskeyAdapterInterface {

	/**
	 * @return PasskeyRegistrationOptionsPayload
	 */
	public function startRegistration( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array;

	/**
	 * @return PasskeyAuthenticationOptionsPayload
	 */
	public function startAuthentication( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array;

	/**
	 * @param PasskeyRegistrationOptionsPayload $registrationOptions
	 * @return PasskeyCredentialRecordPayload
	 */
	public function verifyRegistration(
		string $rawResponseJson,
		array $registrationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array;

	/**
	 * @param PasskeyAuthenticationOptionsPayload $authenticationOptions
	 * @return PasskeyCredentialRecordPayload
	 */
	public function verifyAuthentication(
		string $rawResponseJson,
		array $authenticationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array;
}

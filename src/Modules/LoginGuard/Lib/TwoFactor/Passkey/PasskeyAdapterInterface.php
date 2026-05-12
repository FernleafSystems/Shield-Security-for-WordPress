<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeySourcesHandler;

interface PasskeyAdapterInterface {

	public function startRegistration( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array;

	public function startAuthentication( PasskeyAdapterContext $context, PasskeySourcesHandler $sourceRepo ) :array;

	public function verifyRegistration(
		string $rawResponseJson,
		array $registrationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array;

	public function verifyAuthentication(
		string $rawResponseJson,
		array $authenticationOptions,
		PasskeyAdapterContext $context,
		PasskeySourcesHandler $sourceRepo
	) :array;
}

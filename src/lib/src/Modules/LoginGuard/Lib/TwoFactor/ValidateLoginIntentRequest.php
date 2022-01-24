<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	NoActiveProvidersForUserException,
	NoLoginIntentForUserException
};
use FernleafSystems\Wordpress\Services\Services;

class ValidateLoginIntentRequest {

	use MfaControllerConsumer;

	/**
	 * @throws CouldNotValidate2FA
	 * @throws NoActiveProvidersForUserException
	 * @throws NoLoginIntentForUserException
	 */
	public function run( \WP_User $user, bool $removeIntentOnFailure = false ) :bool {
		$mfaCon = $this->getMfaCon();

		if ( !$mfaCon->hasLoginIntent( $user ) ) {
			throw new NoLoginIntentForUserException();
		}

		$providers = $mfaCon->getProvidersForUser( $user, true );
		if ( empty( $providers ) ) {
			throw new NoActiveProvidersForUserException();
		}

		$validated = false;
		foreach ( $providers as $provider ) {
			if ( $provider->validateLoginIntent( $user ) ) {
				$provider->postSuccessActions( $user );
				$validated = true;
				break;
			}
		}

		// Always remove intent after success, but also after failure if multiple attempts are permitted.
		if ( $validated || $removeIntentOnFailure ) {
			$mfaCon->removeLoginIntent( $user );
		}

		if ( !$validated ) {
			throw new CouldNotValidate2FA();
		}

		return true;
	}
}
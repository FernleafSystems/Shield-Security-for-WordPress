<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	NoActiveProvidersForUserException,
	InvalidLoginIntentException,
	TooManyAttemptsException
};

class LoginIntentRequestValidate {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpUserConsumer;

	/**
	 * @throws CouldNotValidate2FA
	 * @throws NoActiveProvidersForUserException
	 * @throws InvalidLoginIntentException
	 * @throws TooManyAttemptsException
	 */
	public function run( string $plainNonce ) :bool {
		/** @var Shield\Modules\LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$user = $this->getWpUser();

		if ( !$mfaCon->verifyLoginNonce( $user, $plainNonce ) ) {
			throw new InvalidLoginIntentException();
		}

		$providers = $mfaCon->getProvidersForUser( $user, true );
		if ( empty( $providers ) ) {
			throw new NoActiveProvidersForUserException();
		}

		$validated = false;
		foreach ( $providers as $provider ) {
			$provider->setUser( $user );
			if ( $provider->validateLoginIntent( $mfaCon->findHashedNonce( $user, $plainNonce ) ) ) {
				$provider->postSuccessActions();
				$validated = true;
				break;
			}
		}

		if ( !$validated ) {
			throw new CouldNotValidate2FA();
			if ( empty( $mfaCon->getActiveLoginIntents( $user )[ $plainNonce ] ) ) {
				throw new TooManyAttemptsException();
			}
		}

		// Always remove intents after success.
		$this->getCon()->getUserMeta( $user )->login_intents = [];

		return true;
	}
}
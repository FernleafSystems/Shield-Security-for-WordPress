<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	NoActiveProvidersForUserException,
	NoLoginIntentForUserException,
	TooManyAttemptsException
};

class LoginIntentRequestValidate {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpUserConsumer;

	/**
	 * @throws CouldNotValidate2FA
	 * @throws NoActiveProvidersForUserException
	 * @throws NoLoginIntentForUserException
	 * @throws TooManyAttemptsException
	 */
	public function run( string $loginNonce, bool $removeNonceOnFailure = false ) :bool {
		/** @var Shield\Modules\LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$user = $this->getWpUser();

		if ( empty( $mfaCon->getActiveLoginIntents( $user )[ $loginNonce ] ) ) {
			throw new NoLoginIntentForUserException();
		}

		$providers = $mfaCon->getProvidersForUser( $user, true );
		if ( empty( $providers ) ) {
			throw new NoActiveProvidersForUserException();
		}

		$validated = false;
		foreach ( $providers as $provider ) {
			$provider->setUser( $user );
			if ( $provider->validateLoginIntent() ) {
				$provider->postSuccessActions();
				$validated = true;
				break;
			}
		}

		// Always remove intent after success, otherwise increment attempts.
		$intents = $mfaCon->getActiveLoginIntents( $user );
		if ( $validated ) {
			unset( $intents[ $loginNonce ] );
			$this->getCon()->getUserMeta( $user )->login_intents = $intents;
		}
		else {
			$intents = $mfaCon->getActiveLoginIntents( $user );
			$intents[ $loginNonce ][ 'attempts' ]++;
		}
		$this->getCon()->getUserMeta( $user )->login_intents = $intents;

		if ( !$validated ) {
			if ( empty( $mfaCon->getActiveLoginIntents( $user )[ $loginNonce ] ) ) {
				throw new TooManyAttemptsException();
			}
			throw new CouldNotValidate2FA();
		}

		return true;
	}
}
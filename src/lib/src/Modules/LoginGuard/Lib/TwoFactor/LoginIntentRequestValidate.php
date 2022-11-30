<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	InvalidLoginIntentException,
	LoginCancelException,
	NoActiveProvidersForUserException,
	OtpVerificationFailedException,
	TooManyAttemptsException
};

class LoginIntentRequestValidate {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpUserConsumer;

	/**
	 * @throws CouldNotValidate2FA
	 * @throws InvalidLoginIntentException
	 * @throws LoginCancelException
	 * @throws NoActiveProvidersForUserException
	 * @throws OtpVerificationFailedException
	 * @throws TooManyAttemptsException
	 */
	public function run( string $plainNonce, bool $isCancel = false ) :bool {
		/** @var Shield\Modules\LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$user = $this->getWpUser();

		if ( !$mfaCon->verifyLoginNonce( $user, $plainNonce ) ) {
			throw new InvalidLoginIntentException();
		}

		if ( $isCancel ) {
			// only allowed to cancel if the intent is verified.
			throw new LoginCancelException();
		}

		$providers = $mfaCon->getProvidersActiveForUser( $user );
		if ( empty( $providers ) ) {
			throw new NoActiveProvidersForUserException();
		}

		$validated = false;
		foreach ( $providers as $provider ) {
			$provider->setUser( $user );
			try {
				if ( $provider->validateLoginIntent( $mfaCon->findHashedNonce( $user, $plainNonce ) ) ) {
					$provider->postSuccessActions();
					$this->auditLoginIntent( true, $provider->getProviderName() );
					$validated = true;
					break;
				}
			}
			catch ( Exceptions\OtpNotPresentException | Exceptions\ProviderNotActiveForUserException $e ) {
				// Nothing to do here.
			}
			catch ( Exceptions\OtpVerificationFailedException $e ) {
				$this->auditLoginIntent( false, $provider->getProviderName() );
				throw $e;
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

	protected function auditLoginIntent( bool $success, string $providerName ) {
		$this->getCon()->fireEvent(
			$success ? '2fa_verify_success' : '2fa_verify_fail',
			[
				'audit_params' => [
					'user_login' => $this->getWpUser()->user_login,
					'method'     => $providerName,
				]
			]
		);
	}
}
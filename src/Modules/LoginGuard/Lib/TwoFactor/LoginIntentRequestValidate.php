<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	InvalidLoginIntentException,
	LoginCancelException,
	NoActiveProvidersForUserException,
	OtpVerificationFailedException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;

class LoginIntentRequestValidate {

	use PluginControllerConsumer;
	use WpUserConsumer;

	/**
	 * @throws CouldNotValidate2FA
	 * @throws InvalidLoginIntentException
	 * @throws LoginCancelException
	 * @throws NoActiveProvidersForUserException
	 * @throws OtpVerificationFailedException
	 * @throws TooManyAttemptsException
	 */
	public function run( string $plainNonce, bool $isCancel = false ) :string {
		$mfaCon = self::con()->comps->mfa;
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

		$validatedSlug = null;
		foreach ( $providers as $provider ) {
			try {
				\ob_start();
				if ( $provider->validateLoginIntent( $mfaCon->findHashedNonce( $user, $plainNonce ) ) ) {
					$provider->postSuccessActions();
					$this->auditLoginIntent( true, $provider->getProviderName() );
					$validatedSlug = $provider::ProviderSlug();
					break;
				}
			}
			catch ( Exceptions\OtpNotPresentException|Exceptions\ProviderNotActiveForUserException $e ) {
				// Nothing to do here.
			}
			catch ( Exceptions\OtpVerificationFailedException $e ) {
				$this->auditLoginIntent( false, $provider->getProviderName() );
				throw $e;
			}
			finally {
				\ob_end_clean();
			}
		}

		if ( empty( $validatedSlug ) ) {
			throw new CouldNotValidate2FA();
			if ( empty( $mfaCon->getActiveLoginIntents( $user )[ $plainNonce ] ) ) {
				throw new TooManyAttemptsException();
			}
		}

		// Always remove intents after success.
		self::con()->user_metas->for( $user )->login_intents = [];

		return $validatedSlug;
	}

	protected function auditLoginIntent( bool $success, string $providerName ) {
		self::con()->fireEvent(
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
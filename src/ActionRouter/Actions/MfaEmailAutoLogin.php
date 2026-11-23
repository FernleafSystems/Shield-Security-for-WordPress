<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	Exceptions\CouldNotValidate2FA,
	Exceptions\InvalidLoginIntentException,
	Exceptions\NoActiveProvidersForUserException,
	Exceptions\OtpVerificationFailedException,
	Exceptions\TooManyAttemptsException,
	LoginIntentRequestValidate,
	Provider\Email
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * The one-click login link in a 2FA email.
 */
class MfaEmailAutoLogin extends BaseAction {

	use AuthNotRequired;

	public const SLUG = 'mfa_email_auto_login';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		$con = self::con();

		// Every field is read before anything happens, so a malformed one can't fail after login.
		$fields = $this->fields();
		$userID = $fields->positiveInt( 'user_id' );
		$loginNonce = $fields->string( 'login_nonce' );
		$redirectTo = $fields->string( 'redirect_to', '' );

		$user = Services::WpUsers()->getUserById( $userID );
		if ( empty( $user ) ) {
			throw new ActionException( __( 'No such user', 'wp-simple-firewall' ) );
		}
		$otpField = ( new Email( $user ) )->getLoginIntentFormParameter();
		$otp = $fields->string( $otpField );

		// The link logs in through the same 2FA check as the login form. Only the email code is passed, so the link
		// can only ever count as an email login.
		try {
			( new LoginIntentRequestValidate() )
				->setWpUser( $user )
				->run( $loginNonce, [ $otpField => $otp ] );
			$success = true;
		}
		catch ( InvalidLoginIntentException|TooManyAttemptsException $e ) {
			throw new ActionException( __( 'Invalid login nonce', 'wp-simple-firewall' ) );
		}
		catch ( NoActiveProvidersForUserException|CouldNotValidate2FA $e ) {
			throw new ActionException( __( 'No email provider for user.', 'wp-simple-firewall' ) );
		}
		catch ( OtpVerificationFailedException $e ) {
			$success = false;
		}

		if ( $success ) {
			wp_set_auth_cookie( $userID, true );
			$con->comps->events->fireEvent( '2fa_success' );
			$con->comps->login_success->recordSuccessfulLogin( $user );
		}

		$this->response()
			 ->setPayloadSuccess( $success )
			 ->setPayloadRedirectNextStep( empty( $redirectTo ) ? Services::WpGeneral()->getHomeUrl() : $redirectTo );
	}

	protected function getRequiredDataKeys() :array {
		// The email code is required too; its field is named by the email provider and read in exec().
		return [
			'login_nonce',
			'user_id',
		];
	}
}

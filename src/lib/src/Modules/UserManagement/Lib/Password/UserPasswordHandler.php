<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\{
	ModConsumer,
	Strings
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;
use ZxcvbnPhp\Zxcvbn;

/**
 * Referenced some of https://github.com/BenjaminNelan/PwnedPasswordChecker
 */
class UserPasswordHandler {

	use ExecOnce;
	use ModConsumer;
	use WpLoginCapture;

	protected function run() {

		$this->setupLoginCaptureHooks();
		add_action( 'after_password_reset', [ $this, 'onPasswordReset' ] );

		if ( $this->opts()->isPasswordPoliciesEnabled() ) {
			add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
			add_filter( 'registration_errors', [ $this, 'checkPassword' ], 100 );
			add_action( 'user_profile_update_errors', [ $this, 'checkPassword' ], 100 );
			add_action( 'validate_password_reset', [ $this, 'checkPassword' ], 100 );
		}
	}

	protected function captureLogin( \WP_User $user ) {
		$failed = false;

		if ( $this->opts()->isPasswordPoliciesEnabled() ) {
			$password = $this->getLoginPassword();
			if ( Services::Request()->isPost() && !empty( $password ) ) {
				try {
					$this->applyPasswordChecks( $password );
				}
				catch ( Exceptions\PwnedApiFailedException $e ) {
					// We don't fail when the PWNED API is not available.
				}
				catch ( Exceptions\PasswordTooWeakException|Exceptions\PasswordIsPwnedException $e ) {
					$failed = true;
				}
				$this->con()->user_metas->for( $user )->pass_check_failed_at = $failed ?
					Services::Request()->ts() : 0;
			}
		}

		if ( !$failed ) {
			$this->con()->user_metas->for( $user )->updatePasswordStartedAt( $user->user_pass );
		}
	}

	public function onWpLoaded() {
		if ( is_admin() && !Services::WpGeneral()->isAjax() && !Services::Request()->isPost()
			 && Services::WpUsers()->isUserLoggedIn() ) {
			$this->processExpiredPassword();
			$this->processFailedCheckPassword();
		}
	}

	public function onPasswordReset( $user ) {
		if ( $user instanceof \WP_User && $user->ID > 0 ) {
			$meta = $this->con()->user_metas->for( $user );
			unset( $meta->pass_hash );
			$meta->updatePasswordStartedAt( $user->user_pass );
		}
	}

	/**
	 * only called if password policies option is enabled.
	 */
	private function processExpiredPassword() {
		$opts = $this->opts();
		$expireDays = $opts->getOpt( 'pass_expire' );
		if ( $expireDays > 0 ) {
			$startedAt = $this->con()->user_metas->current()->record->pass_started_at;
			if ( $startedAt > 0 && ( Services::Request()->ts() - $startedAt > $opts->getPassExpireTimeout() ) ) {
				$this->con()->fireEvent( 'password_expired', [
					'audit_params' => [
						'user_login' => Services::WpUsers()->getCurrentWpUsername()
					]
				] );
				if ( !Services::WpGeneral()->isAjax() ) {
					$this->redirectToResetPassword(
						sprintf( __( 'Your password has expired (after %s days).', 'wp-simple-firewall' ), $expireDays )
					);
				}
			}
		}
	}

	/**
	 * only called if password policies option is enabled.
	 */
	private function processFailedCheckPassword() {
		$meta = $this->con()->user_metas->current();
		if ( $this->opts()->isOpt( 'pass_force_existing', 'Y' ) && $meta->pass_check_failed_at > 0 ) {
			$this->redirectToResetPassword(
				__( "Your password doesn't meet requirements set by your security administrator.", 'wp-simple-firewall' )
			);
		}
	}

	/**
	 * IMPORTANT: User must be logged-in for this to work correctly
	 * We have a 2 minute delay between redirects because some custom user forms redirect to custom
	 * password reset pages. This prevents users following this flow.
	 * @uses wp_redirect()
	 */
	private function redirectToResetPassword( string $msg ) {
		$con = $this->con();
		$now = Services::Request()->ts();

		$meta = $this->con()->user_metas->current();
		if ( $now - $meta->pass_reset_last_redirect_at > \MINUTE_IN_SECONDS*2 ) {

			$meta->pass_reset_last_redirect_at = $now;

			$WPU = Services::WpUsers();
			$action = Services::Request()->query( 'action' );
			$user = $WPU->getCurrentWpUser();
			if ( $user && ( !Services::WpGeneral()->isLoginUrl() || !\in_array( $action, [ 'rp', 'resetpass' ] ) ) ) {

				$msg .= ' '.__( 'For your security, please use the password section below to update your password.', 'wp-simple-firewall' );
				$con->getAdminNotices()->addFlash( $msg, $user, true, true );
				$con->fireEvent( 'password_policy_force_change', [
					'audit_params' => [
						'user_login' => $user->user_login
					]
				] );
				Services::Response()->redirect( $WPU->getPasswordResetUrl( $user ) );
			}
		}
	}

	/**
	 * @param \WP_Error $wpErrors
	 * @return \WP_Error
	 */
	public function checkPassword( $wpErrors ) {

		if ( is_wp_error( $wpErrors ) && empty( $wpErrors->get_error_code() ) ) {
			$password = $this->getLoginPassword();
			if ( !empty( $password ) ) {
				$failureMsg = '';
				try {
					$this->applyPasswordChecks( $password );
					$checksFailed = false;
				}
				catch ( Exceptions\PwnedApiFailedException $e ) {
					$checksFailed = false;
					// We don't fail when the PWNED API is not available.
				}
				catch ( Exceptions\PasswordTooWeakException|Exceptions\PasswordIsPwnedException $e ) {
					$checksFailed = true;
					$failureMsg = $e->getMessage();
				}

				if ( $checksFailed ) {
					$msg = __( 'Your security administrator has imposed requirements for password quality.', 'wp-simple-firewall' );
					if ( !empty( $failureMsg ) ) {
						$msg .= sprintf( '<br/>%s: %s', __( 'Reason', 'wp-simple-firewall' ), $failureMsg );
					}
					$wpErrors->add( 'shield_password_policy', $msg );
					$this->con()->fireEvent( 'password_policy_block' );
				}
				elseif ( Services::WpUsers()->isUserLoggedIn() ) {
					$this->con()->user_metas->current()->pass_check_failed_at = 0;
				}
			}
		}

		return $wpErrors;
	}

	/**
	 * @throws Exceptions\PasswordIsPwnedException
	 * @throws Exceptions\PasswordTooWeakException
	 * @throws Exceptions\PwnedApiFailedException
	 */
	private function applyPasswordChecks( string $password ) {
		$opts = $this->opts();

		if ( $this->con()->caps->canUserPasswordPolicies() ) {
			$this->testPasswordMeetsMinimumStrength( $password );
		}
		if ( $opts->isPassPreventPwned() ) {
			$this->sendRequestToPwnedRange( $password );
		}
	}

	/**
	 * @throws Exceptions\PasswordTooWeakException
	 */
	private function testPasswordMeetsMinimumStrength( string $password ) :bool {
		$score = (int)( new Zxcvbn() )->passwordStrength( $password )[ 'score' ];

		if ( $score < $this->opts()->getOpt( 'pass_min_strength' ) ) {
			/** @var Strings $str */
			$str = $this->mod()->getStrings();
			throw new Exceptions\PasswordTooWeakException(
				sprintf( "Password strength (%s) doesn't meet the minimum required strength (%s).",
					$str->getPassStrengthName( $score ),
					$str->getPassStrengthName( $this->opts()->getOpt( 'pass_min_strength' ) )
				)
			);
		}

		return true;
	}

	/**
	 * @throws Exceptions\PasswordIsPwnedException
	 * @throws Exceptions\PwnedApiFailedException
	 */
	private function sendRequestToPwnedRange( string $password ) :int {
		$req = Services::HttpRequest();

		$passwordSHA1 = \strtoupper( \hash( 'sha1', $password ) );
		$substrPasswordSHA1 = \substr( $passwordSHA1, 0, 5 );

		$success = $req->get(
			sprintf( '%s/%s', $this->opts()->getDef( 'pwned_api_url_password_range' ), $substrPasswordSHA1 ),
			[
				'headers' => [ 'user-agent' => sprintf( '%s WP Plugin-v%s', 'Shield', $this->con()->getVersion() ) ]
			]
		);

		$error = '';
		if ( !$success ) {
			$error = 'API request failed';
		}
		else {
			$httpCode = (int)$req->lastResponse->getCode();
			if ( empty( $httpCode ) ) {
				$error = 'No response code available from the Pwned API';
			}
			elseif ( $httpCode !== 200 ) {
				$error = 'The response from the Pwned API was unexpected';
			}
			elseif ( \strlen( (string)$req->lastResponse->body ) === 0 ) {
				$error = 'The response from the Pwned API was empty';
			}
		}

		if ( !empty( $error ) ) {
			throw new Exceptions\PwnedApiFailedException( '[Pwned Password API Request] '.$error );
		}

		$body = \strtoupper( \trim( $req->lastResponse->body ) )."\n";
		if ( \preg_match( sprintf( '#%s:([0-9]+)\s#', \substr( $passwordSHA1, 5 ) ), $body, $matches ) ) {
			$countPwned = $matches[ 1 ];
			throw new Exceptions\PasswordIsPwnedException(
				\implode( ' ', [
					__( 'Please supply a different password as this password has been pwned.', 'wp-simple-firewall' ),
					sprintf( '(<a href="%s" target="_blank">%s</a>)',
						'https://shsec.io/la',
						sprintf( _n( '%s time', '%s times', $countPwned, 'wp-simple-firewall' ), $countPwned )
					)
				] ),
				$countPwned
			);
		}

		return 0;
	}
}
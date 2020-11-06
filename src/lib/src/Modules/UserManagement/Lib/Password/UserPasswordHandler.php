<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Referenced some of https://github.com/BenjaminNelan/PwnedPasswordChecker
 * Class UserPasswordController
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password
 */
class UserPasswordHandler {

	use ModConsumer;
	use OneTimeExecute;
	use WpLoginCapture;

	protected function canRun() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		return $opts->isPasswordPoliciesEnabled();
	}

	protected function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( 'password_reset', function ( $user ) {
			if ( $user instanceof \WP_User ) {
				$this->onPasswordReset( $user );
			}
		}, 100, 1 );
		add_filter( 'registration_errors', [ $this, 'checkPassword' ], 100, 3 );
		add_action( 'user_profile_update_errors', [ $this, 'checkPassword' ], 100, 3 );
		add_action( 'validate_password_reset', [ $this, 'checkPassword' ], 100, 3 );
	}

	protected function captureLogin( \WP_User $user ) {
		$password = $this->getLoginPassword();

		if ( Services::Request()->isPost() && !empty( $password ) ) {
			try {
				$this->applyPasswordChecks( $password );
				$failed = false;
			}
			catch ( \Exception $e ) {
				$failed = ( $e->getCode() != 999 ); // We don't fail when the PWNED API is not available.
			}
			$this->setPasswordFailedFlag( $user, $failed );
		}
	}

	public function onWpLoaded() {
		if ( is_admin() && !Services::WpGeneral()->isAjax() && !Services::Request()->isPost()
			 && Services::WpUsers()->isUserLoggedIn() ) {
			$this->processExpiredPassword();
			$this->processFailedCheckPassword();
		}
	}

	private function onPasswordReset( \WP_User $user ) {
		if ( $user->ID > 0 ) {
			$meta = $this->getCon()->getUserMeta( $user );
			unset( $meta->pass_hash );
			$meta->pass_started_at = 0;
		}
	}

	private function processExpiredPassword() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isPassExpirationEnabled() ) {
			$passStartedAt = (int)$this->getCon()->getCurrentUserMeta()->pass_started_at;
			if ( $passStartedAt > 0 ) {
				if ( Services::Request()->ts() - $passStartedAt > $opts->getPassExpireTimeout() ) {
					$this->getCon()->fireEvent( 'pass_expired' );
					$this->redirectToResetPassword(
						sprintf( __( 'Your password has expired (after %s days).', 'wp-simple-firewall' ), $opts->getPassExpireDays() )
					);
				}
			}
		}
	}

	private function processFailedCheckPassword() {
		$meta = $this->getCon()->getCurrentUserMeta();

		$checkFailed = $this->getOptions()->isOpt( 'pass_force_existing', 'Y' )
					   && isset( $meta->pass_check_failed_at ) && $meta->pass_check_failed_at > 0;

		if ( $checkFailed ) {
			$this->redirectToResetPassword(
				__( "Your password doesn't meet requirements set by your security administrator.", 'wp-simple-firewall' )
			);
		}
	}

	/**
	 * IMPORTANT: User must be logged-in for this to work correctly
	 * We have a 2 minute delay between redirects because some custom user forms redirect to custom
	 * password reset pages. This prevents users following this flow.
	 * @param string $msg
	 * @uses wp_redirect()
	 */
	private function redirectToResetPassword( string $msg ) {
		$nNow = Services::Request()->ts();

		$oMeta = $this->getCon()->getCurrentUserMeta();
		$nLastRedirect = (int)$oMeta->pass_reset_last_redirect_at;
		if ( $nNow - $nLastRedirect > MINUTE_IN_SECONDS*2 ) {

			$oMeta->pass_reset_last_redirect_at = $nNow;

			$oWpUsers = Services::WpUsers();
			$sAction = Services::Request()->query( 'action' );
			$oUser = $oWpUsers->getCurrentWpUser();
			if ( $oUser && ( !Services::WpGeneral()->isLoginUrl() || !in_array( $sAction, [ 'rp', 'resetpass' ] ) ) ) {

				$msg .= ' '.__( 'For your security, please use the password section below to update your password.', 'wp-simple-firewall' );
				$this->getMod()
					 ->setFlashAdminNotice( $msg, true, true );
				$this->getCon()->fireEvent( 'password_policy_force_change' );
				Services::Response()->redirect( $oWpUsers->getPasswordResetUrl( $oUser ) );
			}
		}
	}

	/**
	 * @param \WP_Error $wpErrors
	 * @return \WP_Error
	 */
	public function checkPassword( $wpErrors ) {
		$aExistingCodes = $wpErrors->get_error_code();
		if ( empty( $aExistingCodes ) ) {
			$password = $this->getLoginPassword();
			if ( !empty( $password ) ) {
				$aFailureMsg = '';
				try {
					$this->applyPasswordChecks( $password );
					$bChecksPassed = true;
				}
				catch ( \Exception $e ) {
					$bChecksPassed = ( $e->getCode() === 999 );
					$aFailureMsg = $e->getMessage();
				}

				if ( $bChecksPassed ) {
					if ( Services::WpUsers()->isUserLoggedIn() ) {
						$this->getCon()->getCurrentUserMeta()->pass_check_failed_at = 0;
					}
				}
				else {
					$msg = __( 'Your security administrator has imposed requirements for password quality.', 'wp-simple-firewall' );
					if ( !empty( $aFailureMsg ) ) {
						$msg .= '<br/>'.sprintf( __( 'Reason', 'wp-simple-firewall' ).': '.$aFailureMsg );
					}
					$wpErrors->add( 'shield_password_policy', $msg );
					$this->getCon()->fireEvent( 'password_policy_block' );
				}
			}
		}

		return $wpErrors;
	}

	/**
	 * @param string $password
	 * @throws \Exception
	 */
	private function applyPasswordChecks( string $password ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		if ( $opts->getPassMinLength() > 0 ) {
			$this->testPasswordMeetsMinimumLength( $password, $opts->getPassMinLength() );
		}
		if ( $opts->getPassMinStrength() > 0 ) {
			$this->testPasswordMeetsMinimumStrength( $password, $opts->getPassMinStrength() );
		}
		if ( $opts->isPassPreventPwned() ) {
			$this->sendRequestToPwnedRange( $password );
		}
	}

	/**
	 * @param string $password
	 * @param int    $min
	 * @return bool
	 * @throws \Exception
	 */
	private function testPasswordMeetsMinimumLength( string $password, int $min ) {
		$length = strlen( $password );
		if ( $length < $min ) {
			throw new \Exception( sprintf( __( 'Password length (%s) too short (min: %s characters)', 'wp-simple-firewall' ), $length, $min ) );
		}
		return true;
	}

	/**
	 * @param string $password
	 * @param int    $min
	 * @return bool
	 * @throws \Exception
	 */
	private function testPasswordMeetsMinimumStrength( string $password, int $min ) {
		$aResults = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( $password );

		$nScore = $aResults[ 'score' ];

		if ( $nScore < $min ) {
			/** @var UserManagement\ModCon $mod */
			$mod = $this->getMod();
			throw new \Exception( sprintf( "Password strength (%s) doesn't meet the minimum required strength (%s).",
				$mod->getPassStrengthName( $nScore ), $mod->getPassStrengthName( $min ) ) );
		}
		return true;
	}

	/**
	 * Unused
	 * @return bool
	 * private function verifyApiAccess() {
	 * try {
	 * $this->sendRequestToPwnedRange( 'P@ssw0rd' );
	 * }
	 * catch ( \Exception $oE ) {
	 * return false;
	 * }
	 * return true;
	 * }
	 */

	/**
	 * @param string $password
	 * @return bool
	 * @throws \Exception
	 */
	private function sendRequestToPwnedRange( string $password ) {
		$oHttpReq = Services::HttpRequest();

		$sPassHash = strtoupper( hash( 'sha1', $password ) );
		$sSubHash = substr( $sPassHash, 0, 5 );

		$bSuccess = $oHttpReq->get(
			sprintf( '%s/%s', $this->getOptions()->getDef( 'pwned_api_url_password_range' ), $sSubHash ),
			[
				'headers' => [ 'user-agent' => sprintf( '%s WP Plugin-v%s', 'Shield', $this->getCon()->getVersion() ) ]
			]
		);

		$sError = '';
		$nErrorCode = 2; // Default To Error
		if ( !$bSuccess ) {
			$sError = 'API request failed';
			$nErrorCode = 999; // We don't fail PWNED passwords on failed API requests.
		}
		else {
			$nHttpCode = $oHttpReq->lastResponse->getCode();
			if ( empty( $nHttpCode ) ) {
				$sError = 'Unexpected Error: No response code available from the Pwned API';
			}
			elseif ( $nHttpCode != 200 ) {
				$sError = 'Unexpected Error: The response from the Pwned API was unexpected';
			}
			elseif ( empty( $oHttpReq->lastResponse->body ) ) {
				$sError = 'Unexpected Error: The response from the Pwned API was empty';
			}
			else {
				$nPwnedCount = 0;
				foreach ( array_map( 'trim', explode( "\n", trim( $oHttpReq->lastResponse->body ) ) ) as $sRow ) {
					if ( $sSubHash.substr( strtoupper( $sRow ), 0, 35 ) == $sPassHash ) {
						$nPwnedCount = substr( $sRow, 36 );
						break;
					}
				}
				if ( $nPwnedCount > 0 ) {
					$sError = __( 'Please use a different password.', 'wp-simple-firewall' )
							  .'<br/>'.__( 'This password has been pwned.', 'wp-simple-firewall' )
							  .' '.sprintf(
								  '(<a href="%s" target="_blank">%s</a>)',
								  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
								  sprintf( __( '%s times', 'wp-simple-firewall' ), $nPwnedCount )
							  );
				}
				else {
					// Success: Password is not pwned
					$nErrorCode = 0;
				}
			}
		}

		if ( $nErrorCode != 0 ) {
			throw new \Exception( '[Pwned Request] '.$sError, $nErrorCode );
		}

		return true;
	}

	/**
	 * @param \WP_User $user
	 * @param bool     $failed
	 */
	private function setPasswordFailedFlag( \WP_User $user, bool $failed = false ) {
		$this->getCon()
			 ->getUserMeta( $user )
			->pass_check_failed_at = $failed ? Services::Request()->ts() : 0;
	}
}
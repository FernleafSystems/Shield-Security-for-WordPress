<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_UserManagement_Passwords extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		add_action( 'password_reset', [ $this, 'onPasswordReset' ], 100, 1 );
		add_filter( 'registration_errors', [ $this, 'checkPassword' ], 100, 3 );
		add_action( 'user_profile_update_errors', [ $this, 'checkPassword' ], 100, 3 );
		add_action( 'validate_password_reset', [ $this, 'checkPassword' ], 100, 3 );
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $user
	 */
	public function onWpLogin( $sUsername, $user ) {
		$this->captureLogin( $user );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->captureLogin( Services::WpUsers()->getUserById( $nUserId ) );
	}

	/**
	 * @param \WP_User $user
	 */
	private function captureLogin( $user ) {
		$password = $this->getLoginPassword();

		if ( $user instanceof \WP_User
			 && Services::Request()->isPost()
			 && !$this->isLoginCaptured() && !empty( $password ) ) {

			$this->setLoginCaptured();
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

	/**
	 * @param \WP_User $user
	 */
	public function onPasswordReset( $user ) {
		if ( $user instanceof \WP_User && $user->ID > 0 ) {
			$meta = $this->getCon()->getUserMeta( $user );
			unset( $meta->pass_hash );
			$meta->pass_started_at = 0;
		}
	}

	private function processExpiredPassword() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isPassExpirationEnabled() ) {
			$nPassStartedAt = (int)$this->getCon()->getCurrentUserMeta()->pass_started_at;
			if ( $nPassStartedAt > 0 ) {
				if ( Services::Request()->ts() - $nPassStartedAt > $oOpts->getPassExpireTimeout() ) {
					$this->getCon()->fireEvent( 'pass_expired' );
					$this->redirectToResetPassword(
						sprintf( __( 'Your password has expired (after %s days).', 'wp-simple-firewall' ), $oOpts->getPassExpireDays() )
					);
				}
			}
		}
	}

	private function processFailedCheckPassword() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		$oMeta = $this->getCon()->getCurrentUserMeta();

		$checkFailed = $opts->isPassForceUpdateExisting()
					   && isset( $oMeta->pass_check_failed_at ) && $oMeta->pass_check_failed_at > 0;

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
	 * @param string $sMessage
	 * @uses wp_redirect()
	 */
	private function redirectToResetPassword( $sMessage ) {
		$nNow = Services::Request()->ts();

		$oMeta = $this->getCon()->getCurrentUserMeta();
		$nLastRedirect = (int)$oMeta->pass_reset_last_redirect_at;
		if ( $nNow - $nLastRedirect > MINUTE_IN_SECONDS*2 ) {

			$oMeta->pass_reset_last_redirect_at = $nNow;

			$oWpUsers = Services::WpUsers();
			$sAction = Services::Request()->query( 'action' );
			$oUser = $oWpUsers->getCurrentWpUser();
			if ( $oUser && ( !Services::WpGeneral()->isLoginUrl() || !in_array( $sAction, [ 'rp', 'resetpass' ] ) ) ) {

				$sMessage .= ' '.__( 'For your security, please use the password section below to update your password.', 'wp-simple-firewall' );
				$this->getMod()
					 ->setFlashAdminNotice( $sMessage, true, true );
				$this->getCon()->fireEvent( 'password_policy_force_change' );
				Services::Response()->redirect( $oWpUsers->getPasswordResetUrl( $oUser ) );
			}
		}
	}

	/**
	 * @param \WP_Error $oErrors
	 * @return \WP_Error
	 */
	public function checkPassword( $oErrors ) {
		$aExistingCodes = $oErrors->get_error_code();
		if ( empty( $aExistingCodes ) ) {
			$password = $this->getLoginPassword();
			if ( !empty( $password ) ) {
				$aFailureMsg = '';
				try {
					$this->applyPasswordChecks( $password );
					$bChecksPassed = true;
				}
				catch ( \Exception $oE ) {
					$bChecksPassed = ( $oE->getCode() === 999 );
					$aFailureMsg = $oE->getMessage();
				}

				if ( $bChecksPassed ) {
					if ( Services::WpUsers()->isUserLoggedIn() ) {
						$this->getCon()->getCurrentUserMeta()->pass_check_failed_at = 0;
					}
				}
				else {
					$sMessage = __( 'Your security administrator has imposed requirements for password quality.', 'wp-simple-firewall' );
					if ( !empty( $aFailureMsg ) ) {
						$sMessage .= '<br/>'.sprintf( __( 'Reason', 'wp-simple-firewall' ).': '.$aFailureMsg );
					}
					$oErrors->add( 'shield_password_policy', $sMessage );
					$this->getCon()->fireEvent( 'password_policy_block' );
				}
			}
		}

		return $oErrors;
	}

	/**
	 * @param string $password
	 * @throws \Exception
	 */
	protected function applyPasswordChecks( string $password ) {
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

	private function getLoginPassword() :string {
		$sPass = '';

		// Edd: edd_user_pass; Woo: password;
		foreach ( [ 'pwd', 'pass1' ] as $key ) {
			$sP = Services::Request()->post( $key );
			if ( !empty( $sP ) ) {
				$sPass = $sP;
				break;
			}
		}
		return $sPass;
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
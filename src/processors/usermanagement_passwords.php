<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Passwords', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

/**
 * Referenced some of https://github.com/BenjaminNelan/PwnedPasswordChecker
 * Class ICWP_WPSF_Processor_UserManagement_Pwned
 */
class ICWP_WPSF_Processor_UserManagement_Passwords extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		add_filter( 'registration_errors', array( $this, 'checkPassword' ), 100, 3 );
		add_action( 'user_profile_update_errors', array( $this, 'checkPassword' ), 100, 3 );
		add_action( 'validate_password_reset', array( $this, 'checkPassword' ), 100, 3 );
		add_filter( 'login_message', array( $this, 'addPasswordResetMessage' ) );

		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ) );
		$this->loadAutoload();
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->captureLogin( $oUser );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->captureLogin( $this->loadWpUsers()->getUserById( $nUserId ) );
	}

	/**
	 * @param WP_User $oUser
	 */
	private function captureLogin( $oUser ) {
		$sPassword = $this->getLoginPassword();

		if ( $this->loadRequest()->isMethodPost() && !$this->isLoginCaptured()
			 && $oUser instanceof WP_User && !empty( $sPassword ) ) {
			$this->setLoginCaptured();
			try {
				$this->applyPasswordChecks( $sPassword );
				$bFailed = false;
			}
			catch ( Exception $oE ) {
				$bFailed = true;
			}
			$this->setPasswordFailedFlag( $oUser, $bFailed );
		}
	}

	public function onWpLoaded() {
		if ( !$this->loadRequest()->isMethodPost() && $this->loadWpUsers()->isUserLoggedIn() ) {
			$this->processExpiredPassword();
			$this->processFailedCheckPassword();
		}
	}

	/**
	 * @param string $sMessage
	 * @return string
	 */
	public function addPasswordResetMessage( $sMessage = '' ) {
		$sFlushed = $this->loadWpNotices()
						 ->flushFlash()
						 ->getFlashText();
		// we overwrite rather than augment the message
		return empty( $sFlushed ) ? $sMessage : sprintf( '<p class="message">%s</p>', $sFlushed );
	}

	private function processExpiredPassword() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$oMeta = $this->getController()->getCurrentUserMeta();

		$nExpireTimeout = $oFO->getPassExpireTimeout();
		if ( $nExpireTimeout > 0 && $oMeta->pass_started_at > 0 ) {
			if ( $this->time() - $oMeta->pass_started_at > $nExpireTimeout ) {
				$this->addToAuditEntry( _wpsf__( 'Forcing user to update expired password.' ) );
				$this->redirectToResetPassword(
					sprintf( _wpsf__( 'Your password has expired (%s days).' ), $oFO->getPassExpireDays() )
				);
			}
		}
	}

	private function processFailedCheckPassword() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$oMeta = $this->getController()->getCurrentUserMeta();

		$bPassCheckFailed = $oFO->isPassForceUpdateExisting()
							&& isset( $oMeta->pass_check_failed_at ) && $oMeta->pass_check_failed_at > 0;

		if ( $bPassCheckFailed ) {
			$this->addToAuditEntry(
				_wpsf__( 'Forcing user to update password that fails to meet policies.' ),
				'1', 'um_password_force_update'
			);
			$this->redirectToResetPassword(
				_wpsf__( "Your password doesn't meet requirements set by your security administrator." )
			);
		}
	}

	/**
	 * @uses wp_redirect()
	 * @param string $sMessage
	 */
	private function redirectToProfile( $sMessage ) {
		$sExtra = _wpsf__( 'For your security, please use the password section below to update your password.' );
		$this->getMod()
			 ->setFlashAdminNotice( $sMessage.' '.$sExtra );

		$this->loadWp()
			 ->doRedirect(
				 self_admin_url( 'profile.php' ),
				 array( $this->getMod()->prefix( 'force-user-password' ) => 1 )
			 );
	}

	/**
	 * @uses wp_redirect()
	 * @param string $sMessage
	 */
	private function redirectToResetPassword( $sMessage ) {

		$oWp = $this->loadWp();
		$oWpUsers = $this->loadWpUsers();
		$sAction = $this->loadRequest()->query( 'action' );
		$oUser = $oWpUsers->getCurrentWpUser();
		if ( $oUser && ( !$oWp->isRequestLoginUrl() || !in_array( $sAction, array( 'rp', 'resetpass' ) ) ) ) {

			$sMessage .= ' '._wpsf__( 'For your security, please use the password section below to update your password.' );
			$this->getMod()
				 ->setFlashAdminNotice( $sMessage );

			$oWp->doRedirect( $oWpUsers->getPasswordResetUrl( $oUser ) );
		}
	}

	/**
	 * @param WP_Error $oErrors
	 * @return WP_Error
	 */
	public function checkPassword( $oErrors ) {
		$aExistingCodes = $oErrors->get_error_code();
		if ( empty( $aExistingCodes ) ) {
			$sPassword = $this->getLoginPassword();

			if ( !empty( $sPassword ) ) {
				try {
					$this->applyPasswordChecks( $sPassword );

					$oWpUser = $this->loadWpUsers();
					if ( $oWpUser->isUserLoggedIn() ) {
						$this->getCurrentUserMeta()->pass_check_failed_at = 0;
					}
				}
				catch ( Exception $oE ) {
					$sMessage = _wpsf__( 'Your security administrator has imposed requirements for password quality.' )
								.'<br/>'.sprintf( _wpsf__( 'Reason' ).': '.$oE->getMessage() );
					$oErrors->add( 'shield_password_policy', $sMessage );

					/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
					$oFO = $this->getMod();
					$oFO->setOptInsightsAt( 'last_password_block_at' );

					$this->addToAuditEntry(
						_wpsf__( 'Blocked attempted password update that failed policy requirements.' ),
						'1', 'um_password_update_blocked'
					);
				}
			}
		}

		return $oErrors;
	}

	/**
	 * @param string $sPassword
	 * @throws Exception
	 */
	protected function applyPasswordChecks( $sPassword ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		$this->testPasswordMeetsMinimumLength( $sPassword );
		$this->testPasswordMeetsMinimumStrength( $sPassword );
		if ( $oFO->isPassPreventPwned() ) {
			$this->sendRequestToPwnedRange( $sPassword );
		}
	}

	/**
	 * @param string $sPassword
	 * @return bool
	 * @throws Exception
	 */
	protected function testPasswordMeetsMinimumStrength( $sPassword ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$nMin = $oFO->getPassMinStrength();

		$oStengther = new \ZxcvbnPhp\Zxcvbn();
		$aResults = $oStengther->passwordStrength( $sPassword );
		$nScore = $aResults[ 'score' ];

		if ( $nMin > 0 && $nScore < $nMin ) {
			throw new Exception( sprintf( "Password strength (%s) doesn't meet the minimum required strength (%s).",
				$oFO->getPassStrengthName( $nScore ), $oFO->getPassStrengthName( $nMin ) ) );
		}
		return true;
	}

	/**
	 * @param string $sPassword
	 * @return bool
	 * @throws Exception
	 */
	protected function testPasswordMeetsMinimumLength( $sPassword ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$nMin = $oFO->getPassMinLength();
		$nLength = strlen( $sPassword );
		if ( $nMin > 0 && $nLength < $nMin ) {
			throw new Exception( sprintf( _wpsf__( 'Password length (%s) too short (min: %s characters)' ), $nLength, $nMin ) );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	protected function verifyApiAccess() {
		try {
			$this->sendRequestToPwnedRange( 'P@ssw0rd' );
		}
		catch ( Exception $oE ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $sPass
	 * @return bool
	 * @throws Exception
	 */
	protected function sendRequestToPwned( $sPass ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$oConn = $oFO->getConn();

		$aResponse = $this->loadFS()->requestUrl(
			sprintf( '%s/%s', $oFO->getDef( 'pwned_api_url_password_single' ), hash( 'sha1', $sPass ) ),
			array(
				'headers' => array(
					'user-agent' => sprintf( '%s WP Plugin-v%s', $oConn->getHumanName(), $oConn->getVersion() )
				)
			),
			true
		);

		$sError = '';
		if ( is_wp_error( $aResponse ) ) {
			$sError = $aResponse->get_error_message();
		}
		else if ( empty( $aResponse ) ) {
			$sError = 'Response was empty';
		}
		else if ( is_array( $aResponse ) ) {
			if ( empty( $aResponse[ 'response' ][ 'code' ] ) ) {
				$sError = 'Unexpected Error: No response code available from the API';
			}
			else if ( $aResponse[ 'response' ][ 'code' ] == 404 ) {
				// means that the password isn't on the pwned list. It's acceptable.
			}
			else if ( empty( $aResponse[ 'body' ] ) ) {
				$sError = 'Unexpected Error: The response from the API was empty';
			}
			else {
				// password pwned
				$nCount = intval( $aResponse[ 'body' ] );
				if ( $nCount == 0 ) {
					$sError = 'Unexpected Error: The API response could not be properly parsed.';
				}
				else {
					$sError = _wpsf__( 'Please use a different password.' )
							  .' '._wpsf__( 'This password has already been pwned.' )
							  .' '.sprintf(
								  '(<a href="%s" target="_blank">%s</a>)',
								  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
								  sprintf( _wpsf__( '%s times' ), $nCount )
							  );
				}
			}
		}

		if ( !empty( $sError ) ) {
			throw new Exception( $sError );
		}

		return true;
	}

	/**
	 * @param string $sPass
	 * @return bool
	 * @throws Exception
	 */
	protected function sendRequestToPwnedRange( $sPass ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$oConn = $oFO->getConn();

		$sPassHash = strtoupper( hash( 'sha1', $sPass ) );
		$sSubHash = substr( $sPassHash, 0, 5 );

		$aResponse = $this->loadFS()->requestUrl(
			sprintf( '%s/%s', $oFO->getDef( 'pwned_api_url_password_range' ), $sSubHash ),
			array(
				'headers' => array(
					'user-agent' => sprintf( '%s WP Plugin-v%s', $oConn->getHumanName(), $oConn->getVersion() )
				)
			),
			true
		);

		$sError = '';
		if ( is_wp_error( $aResponse ) ) {
			$sError = $aResponse->get_error_message();
		}
		else if ( empty( $aResponse ) ) {
			$sError = 'Response was empty';
		}
		else if ( is_array( $aResponse ) ) {
			if ( empty( $aResponse[ 'response' ][ 'code' ] ) ) {
				$sError = 'Unexpected Error: No response code available from the Pwned API';
			}
			else if ( $aResponse[ 'response' ][ 'code' ] != 200 ) {
				$sError = 'Unexpected Error: The response from the Pwned API was unexpected';
			}
			else if ( empty( $aResponse[ 'body' ] ) ) {
				$sError = 'Unexpected Error: The response from the Pwned API was empty';
			}
			else {
				$nCount = 0;
				foreach ( array_map( 'trim', explode( "\n", trim( $aResponse[ 'body' ] ) ) ) as $sRow ) {
					if ( $sSubHash.substr( strtoupper( $sRow ), 0, 35 ) == $sPassHash ) {
						$nCount = substr( $sRow, 36 );
						break;
					}
				}
				if ( $nCount > 0 ) {
					$sError = _wpsf__( 'Please use a different password.' )
							  .'<br/>'._wpsf__( 'This password has been pwned.' )
							  .' '.sprintf(
								  '(<a href="%s" target="_blank">%s</a>)',
								  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
								  sprintf( _wpsf__( '%s times' ), $nCount )
							  );
				}
			}
		}

		if ( !empty( $sError ) ) {
			throw new Exception( $sError );
		}

		return true;
	}

	/**
	 * @return string
	 */
	private function getLoginPassword() {
		$sPass = null;

		// Edd: edd_user_pass; Woo: password;
		foreach ( array( 'pwd', 'pass1' ) as $sKey ) {
			$sP = $this->loadRequest()->post( $sKey );
			if ( !empty( $sP ) ) {
				$sPass = $sP;
				break;
			}
		}
		return $sPass;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bFailed
	 * @return $this
	 */
	private function setPasswordFailedFlag( $oUser, $bFailed = false ) {
		$oMeta = $this->getController()->getUserMeta( $oUser );
		$oMeta->pass_check_failed_at = $bFailed ? $this->time() : 0;
		return $this;
	}
}
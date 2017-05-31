<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Intent', false ) ):
	return;
endif;

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_Intent extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$oLoginTracker = $this->getLoginTrack();

		if ( $this->getIsOption( 'enable_google_authenticator', 'Y' ) ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Google_Authenticator );
			$this->getProcessorGoogleAuthenticator()->run();
		}

		if ( $oFO->getIsEmailAuthenticationEnabled() ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Email );
			$this->getProcessorTwoFactor()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );
			$this->getProcessorYubikey()->run();
		}

		if ( $oLoginTracker->hasFactorsRemainingToTrack() ) {
			if ( $this->loadWpFunctions()->getIsLoginRequest() ) {
				add_filter( 'authenticate', array( $this, 'setUserLoginIntent' ), 100, 1 );
			}
			add_action( 'init', array( $this, 'onWpInit' ), 0 );
		}

		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );
		return true;
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) {

			if ( $this->isCurrentUserSubjectToLoginIntent() ) {
				$this->processUserLoginIntent();
			}
			else if ( $this->getUserLoginIntent() !== false ) {
				// This handles the case where an admin changes a setting while a user is logged-in
				// So to prevent this, we remove any intent for a user that isn't subject to it right now
				$this->removeLoginIntent();
			}
		}
	}

	/**
	 * hooked to 'init' and only run if a user is logged in
	 */
	public function processUserLoginIntent() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		if ( $this->userHasPendingLoginIntent() ) {
			$oDp = $this->loadDataProcessor();

			$bIsLoginIntentSubmission = $oDp->FetchRequest( $oFO->getLoginIntentRequestFlag() ) == 1;
			if ( $bIsLoginIntentSubmission ) {

				if ( $oDp->FetchPost( 'cancel' ) == 1 ) {
					$this->loadWpUsers()->logoutUser(); // clears the login and login intent
					$this->loadWpFunctions()->redirectToLogin();
					return;
				}

				$oLoginTracker = $this->getLoginTrack();
				do_action( $oFO->prefix( 'login-intent-validation' ) );
				if ( $oFO->isChainedAuth() ) {
					$bLoginIntentValidated = !$oLoginTracker->hasUnSuccessfulFactorAuth();
				}
				else {
					$bLoginIntentValidated = $oLoginTracker->hasSuccessfulFactorAuth();
				}

				if ( $bLoginIntentValidated ) {
					$this->removeLoginIntent();
					$sRedirect = $oDp->FetchRequest( 'redirect_to' );
					$this->loadAdminNoticesProcessor()->addFlashMessage(
						_wpsf__( 'Success' ).'! '._wpsf__( 'Thank you for authenticating your login.' ) );
					if ( !empty( $sRedirect ) ) {
						$this->loadWpFunctions()->doRedirect( esc_url( rawurldecode( $sRedirect ) ) );
					}
				}
				else {
					$this->loadAdminNoticesProcessor()->addFlashMessage(
						_wpsf__( 'One or more of your authentication codes failed or was missing' ) );
				}
				$this->loadWpFunctions()->redirectHere();
			}
			if ( $this->printLoginIntentForm() ) {
				die();
			}
		}
		else {
			$nIntent = $this->getUserLoginIntent();
			if ( $nIntent === false ) {
				// the login has already been fully validated and the login intent was deleted.
				// false also means new installation don't get booted out
			}
			else if ( $nIntent > 0 ) { // there was an old login intent
				$this->loadWpUsers()->logoutUser(); // clears the login and login intent
				$this->loadWpFunctions()->redirectHere();
			}
		}
	}

	/**
	 */
	public function onWpLogout() {
		$this->resetUserLoginIntent();
	}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 */
	protected function removeLoginIntent() {
		$this->loadWpUsers()->deleteUserMeta( $this->getOptionKey() );
	}

	/**
	 * Reset will put the counter to zero - this should be used when the user HAS NOT
	 * verified the login intent.  To indicate that they have successfully verified, use removeLoginIntent()
	 */
	public function resetUserLoginIntent() {
		$this->setLoginIntentExpiration( 0 );
	}

	/**
	 * @param int $nExpirationTime
	 * @param null $oUser
	 */
	protected function setLoginIntentExpiration( $nExpirationTime, $oUser = null ) {
		$this->loadWpUsers()->updateUserMeta( $this->getOptionKey(), max( 0, (int)$nExpirationTime ), $oUser );
	}

	/**
	 * @return string
	 */
	protected function getOptionKey() {
		return $this->getFeature()->prefixOptionKey( 'login_intent' );
	}

	/**
	 * If it's a valid login attempt (by password) then $oUser is a WP_User
	 * @param WP_User|WP_Error $oUser
	 * @return WP_User
	 */
	public function setUserLoginIntent( $oUser ) {
		if ( !empty( $oUser ) && ( $oUser instanceof WP_User ) ) {
			$oF = $this->getFeature();
			$nTimeout = (int)apply_filters(
				$oF->prefix( 'login_intent_timeout' ),
				$oF->getDefinition( 'login_intent_timeout' )
			);
			$this->setLoginIntentExpiration($this->time() + MINUTE_IN_SECONDS*$nTimeout, $oUser );
		}
		return $oUser;
	}

	/**
	 * @return bool
	 */
	protected function userHasPendingLoginIntent() {
		return ( $this->getUserLoginIntent() > $this->time() );
	}

	/**
	 * @return bool
	 */
	protected function isCurrentUserSubjectToLoginIntent() {
		return apply_filters( $this->getFeature()->prefixOptionKey( 'user_subject_to_login_intent' ), false );
	}

	/**
	 * @return int|false
	 */
	protected function getUserLoginIntent() {
		return $this->loadWpUsers()->getUserMeta( $this->getOptionKey() );
	}

	/**
	 * @return bool true if valid form printed, false otherwise. Should die() if true
	 */
	public function printLoginIntentForm() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oCon = $this->getController();
		$aLoginIntentFields = apply_filters( $oFO->prefix( 'login-intent-form-fields' ), array() );

		if ( empty( $aLoginIntentFields ) ) {
			return false; // a final guard against displaying an empty form.
		}

		$sMessage = $this->loadAdminNoticesProcessor()
						 ->flushFlashMessage()
						 ->getRawFlashMessageText();

		if ( empty( $sMessage ) ) {
			if ( $oFO->isChainedAuth() ) {
				$sMessage = _wpsf__( 'Please supply all authentication codes' );
			}
			else {
				$sMessage = _wpsf__( 'Please supply at least 1 authentication code' );
			}
			$sMessageType = 'info';
		}
		else {
			$sMessageType = 'warning';
		}

		$sRedirectTo = $this->loadDataProcessor()->FetchGet( 'redirect_to' );
		if ( empty( $sRedirectTo ) ) {
			$sRedirectTo = rawurlencode( esc_url( $this->loadDataProcessor()->getRequestUri() ) );
		}

		$aDisplayData = array(
			'strings' => array(
				'cancel'          => _wpsf__( 'Cancel Login' ),
				'time_remaining'  => _wpsf__( 'Time Remaining' ),
				'calculating'     => _wpsf__( 'Calculating' ) . ' ...',
				'seconds'         => strtolower( _wpsf__( 'Seconds' ) ),
				'login_expired'   => _wpsf__( 'Login Expired' ),
				'verify_my_login' => _wpsf__( 'Verify My Login' ),
				'more_info'       => _wpsf__( 'More Info' ),
				'what_is_this'    => _wpsf__( 'What is this?' ),
				'message'         => $sMessage,
				'page_title'      => sprintf( _wpsf__( '%s Login Verification' ), $oCon->getHumanName() )
			),
			'data'    => array(
				'login_fields'      => $aLoginIntentFields,
				'time_remaining'    => $this->getUserLoginIntent() - $this->time(),
				'message_type'      => $sMessageType,
				'login_intent_flag' => $oFO->getLoginIntentRequestFlag()
			),
			'hrefs'   => array(
				'form_action'   => $this->loadDataProcessor()->getRequestUri(),
				'css_bootstrap' => $oCon->getPluginUrl_Css( 'bootstrap3.min.css' ),
				'js_bootstrap'  => $oCon->getPluginUrl_Js( 'bootstrap3.min.js' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'redirect_to'   => $sRedirectTo,
				'what_is_this'  => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'favicon'       => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			)
		);

		$this->loadRenderer( $this->getController()->getPath_Templates() )
			 ->setTemplate( 'page/login_intent' )
			 ->setRenderVars( $aDisplayData )
			 ->display();

		return true;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorTwoFactor() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_twofactorauth.php' );
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oProc = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_yubikey.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeature() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator
	 */
	protected function getProcessorGoogleAuthenticator() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_googleauthenticator.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator( $this->getFeature() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		if ( !isset( $this->oLoginTrack ) ) {
			require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_track.php' );
			$this->oLoginTrack = new ICWP_WPSF_Processor_LoginProtect_Track();
		}
		return $this->oLoginTrack;
	}

	/**
	 * @param ICWP_WPSF_Processor_LoginProtect_Track $oLoginTrack
	 * @return $this
	 */
	public function setLoginTrack( $oLoginTrack ) {
		$this->oLoginTrack = $oLoginTrack;
		return $this;
	}
}
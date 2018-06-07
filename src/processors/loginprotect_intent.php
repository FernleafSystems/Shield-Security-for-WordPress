<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Intent', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_Intent extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 * @var bool
	 */
	private $bLoginIntentProcessed;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		add_action( 'init', array( $this, 'onWpInit' ), 0 );
		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		// 100 priority is important as this takes priority
		add_filter( $oFO->prefix( 'user_subject_to_login_intent' ), array( $this, 'applyUserCanMfaSkip' ), 100, 2 );

		if ( $oFO->getIfSupport3rdParty() ) {
			add_action( 'wc_social_login_before_user_login', array( $this, 'onWcSocialLogin' ) );
		}
	}

	/**
	 * @param int $nUserId
	 */
	public function onWcSocialLogin( $nUserId ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$oUser = new WP_User( $nUserId );
		if ( $oUser->ID != 0 ) { // i.e. said user id exists.
			$oMeta = $oFO->getUserMeta( $oUser );
			$oMeta->wc_social_login_valid = true;
		}
	}

	public function onWpInit() {
		$this->setupLoginIntent();
	}

	protected function setupLoginIntent() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oWpUsers = $this->loadWpUsers();

		$oLoginTracker = $this->getLoginTrack();

		if ( $oFO->getIsEnabledGoogleAuthenticator() ) {
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
			if ( $this->loadWp()->isRequestUserLogin() || $oFO->getIfSupport3rdParty() ) {
				add_filter( 'authenticate', array( $this, 'initLoginIntent' ), 100, 1 );
			}

			// process the current login intent
			if ( $oWpUsers->isUserLoggedIn() ) {
				if ( $this->isUserSubjectToLoginIntent() ) {
					$this->processLoginIntent();
				}
				else if ( $this->hasLoginIntent() ) {
					// This handles the case where an admin changes a setting while a user is logged-in
					// So to prevent this, we remove any intent for a user that isn't subject to it right now
					$this->removeLoginIntent();
				}
			}
		}
	}

	/**
	 * hooked to 'init' and only run if a user is logged in
	 */
	private function processLoginIntent() {
		$oWpUsers = $this->loadWpUsers();

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		if ( $this->hasValidLoginIntent() ) { // ie. valid login intent present
			$oDp = $this->loadDP();

			$bIsLoginIntentSubmission = $oDp->request( $oFO->getLoginIntentRequestFlag() ) == 1;
			if ( $bIsLoginIntentSubmission ) {

				if ( $oDp->post( 'cancel' ) == 1 ) {
					$oWpUsers->logoutUser(); // clears the login and login intent
					$this->loadWp()->redirectToLogin();
					return;
				}

				if ( $this->getIsLoginIntentValid() ) {

					if ( $oDp->post( 'skip_mfa' ) === 'Y' ) { // store the browser hash
						$oFO->addMfaLoginHash( $oWpUsers->getCurrentWpUser() );
					}

					$this->removeLoginIntent();
					$this->loadAdminNoticesProcessor()->addFlashMessage(
						_wpsf__( 'Success' ).'! '._wpsf__( 'Thank you for authenticating your login.' ) );

					$oFO->setOptInsightsAt( 'last_2fa_login_at' );
				}
				else {
					$this->loadAdminNoticesProcessor()->addFlashMessage(
						_wpsf__( 'One or more of your authentication codes failed or was missing' ) );
				}
				$this->loadWp()->redirectHere();
			}
			if ( $this->printLoginIntentForm() ) {
				die();
			}
		}
		else if ( $this->hasLoginIntent() ) { // there was an old login intent
			$oWpUsers->logoutUser(); // clears the login and login intent
			$this->loadWp()->redirectHere();
		}
		else {
			// no login intent present -
			// the login has already been fully validated and the login intent was deleted.
			// also means new installation don't get booted out
		}
	}

	/**
	 */
	public function onWpLogout() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$this->resetLoginIntent();

		// support for WooCommerce Social Login
		if ( $oFO->getIfSupport3rdParty() ) {
			$oFO->getCurrentUserMeta()->wc_social_login_valid = false;
		}
	}

	/**
	 * If it's a valid login attempt (by password) then $oUser is a WP_User
	 * @param WP_User|WP_Error $oUser
	 * @return WP_User
	 */
	public function initLoginIntent( $oUser ) {
		if ( $oUser instanceof WP_User ) {

			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oF */
			$oF = $this->getFeature();
			if ( !$oF->canUserMfaSkip( $oUser ) ) {
				$nTimeout = (int)apply_filters(
					$oF->prefix( 'login_intent_timeout' ),
					$oF->getDef( 'login_intent_timeout' )
				);
				$this->setLoginIntentExpiresAt( $this->time() + MINUTE_IN_SECONDS*$nTimeout, $oUser );
			}
		}
		return $oUser;
	}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	protected function removeLoginIntent() {
		unset( $this->getCurrentUserMeta()->login_intent_expires_at );
		return $this;
	}

	/**
	 * Reset will put the counter to zero - this should be used when the user HAS NOT
	 * verified the login intent.  To indicate that they have successfully verified, use removeLoginIntent()
	 * @return $this
	 */
	public function resetLoginIntent() {
		$this->setLoginIntentExpiresAt( 0, $this->loadWpUsers()->getCurrentWpUser() );
		return $this;
	}

	/**
	 * @param int     $nExpirationTime
	 * @param WP_User $oUser
	 * @return $this
	 */
	protected function setLoginIntentExpiresAt( $nExpirationTime, $oUser ) {
		if ( $oUser instanceof WP_User ) {
			$oMeta = $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
			$oMeta->login_intent_expires_at = max( 0, (int)$nExpirationTime );
		}
		return $this;
	}

	/**
	 * Must be set with a higher priority than other filters as it will override them
	 * @param bool    $bIsSubjectTo
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function applyUserCanMfaSkip( $bIsSubjectTo, $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		return $bIsSubjectTo && !$oFO->canUserMfaSkip( $oUser );
	}

	/**
	 * @return int
	 */
	protected function getLoginIntentExpiresAt() {
		return (int)$this->getCurrentUserMeta()->login_intent_expires_at;
	}

	/**
	 * @return bool
	 */
	protected function hasLoginIntent() {
		return isset( $this->getCurrentUserMeta()->login_intent_expires_at );
	}

	/**
	 * @return bool
	 */
	protected function hasValidLoginIntent() {
		return $this->hasLoginIntent() && ( $this->getLoginIntentExpiresAt() > $this->time() );
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

		$sRedirectTo = rawurlencode( $this->loadDP()->getRequestUri() ); // not actually used

		$aDisplayData = array(
			'strings' => array(
				'cancel'          => _wpsf__( 'Cancel Login' ),
				'time_remaining'  => _wpsf__( 'Time Remaining' ),
				'calculating'     => _wpsf__( 'Calculating' ).' ...',
				'seconds'         => strtolower( _wpsf__( 'Seconds' ) ),
				'login_expired'   => _wpsf__( 'Login Expired' ),
				'verify_my_login' => _wpsf__( 'Verify My Login' ),
				'more_info'       => _wpsf__( 'More Info' ),
				'what_is_this'    => _wpsf__( 'What is this?' ),
				'message'         => $sMessage,
				'page_title'      => sprintf( _wpsf__( '%s Login Verification' ), $oCon->getHumanName() ),
				'skip_mfa'        => sprintf( _wpsf__( "Don't ask again on this browser for %s day(s)" ), $oFO->getMfaSkip() )
			),
			'data'    => array(
				'login_fields'      => $aLoginIntentFields,
				'time_remaining'    => $this->getLoginIntentExpiresAt() - $this->time(),
				'message_type'      => $sMessageType,
				'login_intent_flag' => $oFO->getLoginIntentRequestFlag()
			),
			'hrefs'   => array(
				'form_action'   => $this->loadDataProcessor()->getRequestUri(),
				'css_bootstrap' => $oCon->getPluginUrl_Css( 'bootstrap4.min.css' ),
				'js_bootstrap'  => $oCon->getPluginUrl_Js( 'bootstrap4.min.js' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'redirect_to'   => $sRedirectTo,
				'what_is_this'  => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'favicon'       => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			),
			'flags'   => array(
				'can_skip_mfa' => $oFO->getMfaSkipEnabled()
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
		require_once( dirname( __FILE__ ).'/loginprotect_twofactorauth.php' );
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oProc = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		require_once( dirname( __FILE__ ).'/loginprotect_yubikey.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeature() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator
	 */
	public function getProcessorGoogleAuthenticator() {
		require_once( dirname( __FILE__ ).'/loginprotect_googleauthenticator.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator( $this->getFeature() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		if ( !isset( $this->oLoginTrack ) ) {
			require_once( dirname( __FILE__ ).'/loginprotect_track.php' );
			$this->oLoginTrack = new ICWP_WPSF_Processor_LoginProtect_Track();
		}
		return $this->oLoginTrack;
	}

	/**
	 * assume that a user is logged in.
	 * @return bool
	 */
	private function getIsLoginIntentValid() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		if ( !$this->isLoginIntentProcessed() ) {
			// This action sets up the necessary login tracker info
			do_action( $oFO->prefix( 'login-intent-validation' ), $this->loadWpUsers()->getCurrentWpUser() );
			$this->setLoginIntentProcessed();
		}
		$oTrk = $this->getLoginTrack();
		return $oFO->isChainedAuth() ? $oTrk->hasUnSuccessfulFactor() : $oTrk->hasSuccessfulFactor();
	}

	/**
	 * @return bool
	 */
	public function isLoginIntentProcessed() {
		return (bool)$this->bLoginIntentProcessed;
	}

	/**
	 * @return $this
	 */
	public function setLoginIntentProcessed() {
		$this->bLoginIntentProcessed = true;
		return $this;
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
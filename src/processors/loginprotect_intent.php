<?php

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
		$oFO = $this->getMod();
		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		// 100 priority is important as this takes priority
//		add_filter( $oFO->prefix( 'user_subject_to_login_intent' ), array( $this, 'applyUserCanMfaSkip' ), 100, 2 );

		if ( $oFO->getIfSupport3rdParty() ) {
			add_action( 'wc_social_login_before_user_login', array( $this, 'onWcSocialLogin' ) );
		}
	}

	/**
	 * @param int $nUserId
	 */
	public function onWcSocialLogin( $nUserId ) {
		$oUser = $this->loadWpUsers()->getUserById( $nUserId );
		if ( $oUser instanceof WP_User ) {
			$this->getCon()->getUserMeta( $oUser )->wc_social_login_valid = true;
		}
	}

	public function onWpInit() {
		parent::onWpInit();

		$this->setupLoginIntent();
	}

	protected function setupLoginIntent() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabledGoogleAuthenticator() ) {
			$this->getProcessorGoogleAuthenticator()->run();
		}

		if ( $oFO->isEmailAuthenticationActive() ) {
			$this->getProcessorEmailAuth()->run();
		}

		if ( $oFO->isYubikeyActive() ) {
			$this->getProcessorYubikey()->run();
		}

		if ( $oFO->isEnabledBackupCodes() ) {
			$this->getProcessorBackupCodes()->run();
		}

		if ( $this->getLoginTrack()->hasFactorsRemainingToTrack() ) {
			if ( $this->loadWp()->isRequestUserLogin() || $oFO->getIfSupport3rdParty() ) {
				/** 20180925 - now using set cookie auth instead so we can capture session */
//				add_action( 'authenticate', array( $this, 'initLoginIntent' ), 100, 1 );
			}

			// process the current login intent
			$oWpUsers = $this->loadWpUsers();
			if ( $oWpUsers->isUserLoggedIn() ) {
				if ( $this->isUserSubjectToLoginIntent() && !$oFO->canUserMfaSkip( $oWpUsers->getCurrentWpUser() ) ) {
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
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->initLoginIntent( $oUser );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->initLoginIntent( $this->loadWpUsers()->getUserById( $nUserId ) );
	}

	/**
	 * @param WP_User|WP_Error $oUser
	 */
	protected function initLoginIntent( $oUser ) {

		if ( !$this->isLoginCaptured() && $oUser instanceof WP_User
			 && $this->getLoginTrack()->hasFactorsRemainingToTrack() ) {

			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oF */
			$oF = $this->getMod();
			if ( !$oF->canUserMfaSkip( $oUser ) ) {
				$nTimeout = (int)apply_filters(
					$oF->prefix( 'login_intent_timeout' ),
					$oF->getDef( 'login_intent_timeout' )
				);
				$this->setLoginIntentExpiresAt( $this->time() + MINUTE_IN_SECONDS*$nTimeout );
			}
		}
	}

	/**
	 * hooked to 'init' and only run if a user is logged-in (not on the login request)
	 */
	private function processLoginIntent() {
		$oWp = $this->loadWp();
		$oWpUsers = $this->loadWpUsers();

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( $this->hasValidLoginIntent() ) { // ie. valid login intent present
			$oReq = $this->loadRequest();

			// Is 2FA/login-intent submit
			if ( $oReq->request( $oFO->getLoginIntentRequestFlag() ) == 1 ) {

				if ( $oReq->post( 'cancel' ) == 1 ) {
					$oWpUsers->logoutUser(); // clears the login and login intent
					$sRedirectHref = $oReq->post( 'cancel_href' );
					empty( $sRedirectHref ) ? $oWp->redirectToLogin() : $oWp->doRedirect( rawurldecode( $sRedirectHref ) );
				}
				else if ( $this->isLoginIntentValid() ) {

					if ( $oReq->post( 'skip_mfa' ) === 'Y' ) { // store the browser hash
						$oFO->addMfaLoginHash( $oWpUsers->getCurrentWpUser() );
					}

					$this->removeLoginIntent();
					$sFlash = _wpsf__( 'Success' ).'! '._wpsf__( 'Thank you for authenticating your login.' );
					if ( $oFO->isEnabledBackupCodes() ) {
						$sFlash .= ' '._wpsf__( 'If you used your Backup Code, you will need to reset it.' ); //TODO::
//								   .' '.sprintf( '<a href="%s">%s</a>', $oWpUsers->getAdminUrl_ProfileEdit(), _wpsf__( 'Go' ) );
					}

					$oFO->setFlashAdminNotice( $sFlash )
						->setOptInsightsAt( 'last_2fa_login_at' );

					$sRedirectHref = $oReq->post( 'redirect_to' );
					empty( $sRedirectHref ) ? $oWp->redirectHere() : $oWp->doRedirect( rawurldecode( $sRedirectHref ) );
				}
				else {
					$oFO->setFlashAdminNotice( _wpsf__( 'One or more of your authentication codes failed or was missing' ), true );
					$this->loadWp()->redirectHere();
				}
				return; // we've redirected anyway.
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
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	private function removeLoginIntent() {
		return $this->setLoginIntentExpiresAt( 0 );
	}

	/**
	 * @param int $nExpirationTime
	 * @return $this
	 */
	protected function setLoginIntentExpiresAt( $nExpirationTime ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->hasSession() ) {
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oFO->getSessionsProcessor()->getDbHandler()->getQueryUpdater();
			$oUpd->updateLoginIntentExpiresAt( $oFO->getSession(), $nExpirationTime );
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
		$oFO = $this->getMod();
		return $bIsSubjectTo && !$oFO->canUserMfaSkip( $oUser );
	}

	/**
	 * @return int
	 */
	protected function getLoginIntentExpiresAt() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->hasSession() ? $oFO->getSession()->login_intent_expires_at : 0;
	}

	/**
	 * @return bool
	 */
	protected function hasLoginIntent() {
		return $this->getLoginIntentExpiresAt() > 0;
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
		$oFO = $this->getMod();
		$oCon = $this->getCon();
		$oReq = $this->loadRequest();
		$aLoginIntentFields = apply_filters( $oFO->prefix( 'login-intent-form-fields' ), array() );

		if ( empty( $aLoginIntentFields ) ) {
			return false; // a final guard against displaying an empty form.
		}

		$sMessage = $this->loadWpNotices()
						 ->flushFlash()
						 ->getFlashText();

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

		$sReferUrl = $oReq->server( 'HTTP_REFERER', '' );
		if ( strpos( $sReferUrl, '?' ) ) {
			list( $sReferUrl, $sReferQuery ) = explode( '?', $sReferUrl, 2 );
		}
		else {
			$sReferQuery = '';
		}

		$sRedirectTo = $oReq->post( 'redirect_to', '' );
		if ( !empty( $sReferQuery ) ) {
			parse_str( $sReferQuery, $aReferQueryItems );
			if ( !empty( $aReferQueryItems[ 'redirect_to' ] ) ) {
				$sRedirectTo = rawurlencode( $aReferQueryItems[ 'redirect_to' ] );
			}
		}

		$sCancelHref = $oReq->post( 'cancel_href', '' );
		if ( empty( $sCancelHref ) && $this->loadDP()->isValidUrl( $sReferUrl ) ) {
			$sCancelHref = rawurlencode( parse_url( $sReferUrl, PHP_URL_PATH ) );
		}

		$aLabels = $oCon->getPluginLabels();
		$sBannerUrl = empty( $aLabels[ 'url_login2fa_logourl' ] ) ? $oCon->getPluginUrl_Image( 'pluginlogo_banner-772x250.png' ) : $aLabels[ 'url_login2fa_logourl' ];
		$nMfaSkip = $oFO->getMfaSkip();
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
				'skip_mfa'        => sprintf(
					_wpsf__( "Don't ask again on this browser for %s." ),
					sprintf( _n( '%s day', '%s days', $nMfaSkip, 'wp-simple-firewall' ), $nMfaSkip )
				)
			),
			'data'    => array(
				'login_fields'      => $aLoginIntentFields,
				'time_remaining'    => $this->getLoginIntentExpiresAt() - $this->time(),
				'message_type'      => $sMessageType,
				'login_intent_flag' => $oFO->getLoginIntentRequestFlag(),
				'page_locale'       => $this->loadWp()->getLocale( '-' )
			),
			'hrefs'   => array(
				'form_action'   => $oReq->getUri(),
				'css_bootstrap' => $oCon->getPluginUrl_Css( 'bootstrap4.min.css' ),
				'js_bootstrap'  => $oCon->getPluginUrl_Js( 'bootstrap4.min.js' ),
				'shield_logo'   => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'redirect_to'   => $sRedirectTo,
				'what_is_this'  => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'cancel_href'   => $sCancelHref
			),
			'imgs'    => array(
				'banner'  => $sBannerUrl,
				'favicon' => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			),
			'flags'   => array(
				'can_skip_mfa'       => $oFO->getMfaSkipEnabled(),
				'show_branded_links' => !$oFO->isWlEnabled(), // white label mitigation
			)
		);

		$this->loadRenderer( $this->getCon()->getPath_Templates() )
			 ->setTemplate( 'page/login_intent' )
			 ->setRenderVars( $aDisplayData )
			 ->display();

		return true;
	}

	/**
	 */
	public function onWpLogout() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		$this->removeLoginIntent();

		// support for WooCommerce Social Login
		if ( $oFO->getIfSupport3rdParty() ) {
			$this->getCon()->getCurrentUserMeta()->wc_social_login_valid = false;
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorEmailAuth() {
		require_once( __DIR__.'/loginprotect_intentprovider_email.php' );
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$oProc = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		require_once( __DIR__.'/loginprotect_intentprovider_yubikey.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getMod() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_BackupCodes
	 */
	public function getProcessorBackupCodes() {
		require_once( __DIR__.'/loginprotect_intentprovider_backup.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_BackupCodes( $this->getMod() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator
	 */
	public function getProcessorGoogleAuthenticator() {
		require_once( __DIR__.'/loginprotect_intentprovider_ga.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator( $this->getMod() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		if ( !isset( $this->oLoginTrack ) ) {
			require_once( __DIR__.'/loginprotect_intent_tracker.php' );
			$this->oLoginTrack = new ICWP_WPSF_Processor_LoginProtect_Track();
		}
		return $this->oLoginTrack;
	}

	/**
	 * assume that a user is logged in.
	 * @return bool
	 */
	private function isLoginIntentValid() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		if ( !$this->isLoginIntentProcessed() ) {
			// This action sets up the necessary login tracker info
			do_action( $oFO->prefix( 'login-intent-validation' ), $this->loadWpUsers()->getCurrentWpUser() );
			$this->setLoginIntentProcessed();
		}
		$oTrk = $this->getLoginTrack();

		// 1st: if backup code was used, then chained auth is irrelevant
		$sBackupStub = ICWP_WPSF_Processor_LoginProtect_Track::Factor_BackupCode;

		// if backup code used, that's successful; or
		// It's not chained and you have any 1 successful; or
		// It's chained (and then you must exclude the backup code.
		$bSuccess = in_array( $sBackupStub, $oTrk->getFactorsSuccessful() )
					|| ( !$oFO->isChainedAuth() && $oTrk->hasSuccessfulFactor() );
		if ( !$bSuccess && $oFO->isChainedAuth() ) {
			$bSuccess = !$oTrk->hasUnSuccessfulFactor()
						|| ( $oTrk->getCountFactorsUnsuccessful() == 1 && in_array( $sBackupStub, $oTrk->getFactorsUnsuccessful() ) );
		}

		return $bSuccess;
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
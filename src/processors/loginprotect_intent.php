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
		$oFO = $this->getFeatureOptions();

		$oLoginTracker = $this->getLoginTrack();

		if ( $this->getIsOption( 'enable_google_authenticator', 'Y' ) ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Google_Authenticator );
//			$this->getProcessorGoogleAuthenticator()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );
//			$this->getProcessorYubikey()->run();
		}

		if ( $oFO->getIsEmailAuthenticationEnabled() ) {
			$oLoginTracker->addFactorToTrack( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Email );
//			$this->getProcessorTwoFactor()->run();
		}

		if ( $oLoginTracker->hasFactorsRemainingToTrack() ) {
			if ( $this->loadWpFunctionsProcessor()->getIsLoginRequest() ) {
				add_filter( 'authenticate', array( $this, 'setUserLoginIntent' ), 100, 1 );
			}
			add_action( 'init', array( $this, 'processUserLoginIntent' ), 100, 1 );
		}

		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );
		return true;
	}

	/**
	 */
	public function onWpLogout() {
		$this->clearUserLoginIntent();
	}

	/**
	 */
	public function clearUserLoginIntent() {
		$this->setLoginIntentExpiration( 0 );
	}

	/**
	 * @param int $nExpirationTime
	 * @param null $oUser
	 */
	protected function setLoginIntentExpiration( $nExpirationTime, $oUser = null ) {
		$this->loadWpUsersProcessor()->updateUserMeta( 'login_intent', max( 0, (int)$nExpirationTime ), $oUser );
	}

	/**
	 * @param WP_User|WP_Error $oUser
	 * @return WP_User
	 */
	public function setUserLoginIntent( $oUser ) {
		$bNeedToSetIntent = $this->getLoginTrack()->hasFactorsRemainingToTrack();
		if ( $bNeedToSetIntent && !empty( $oUser ) && ( $oUser instanceof WP_User ) ) {
			$this->setLoginIntentExpiration($this->time() + MINUTE_IN_SECONDS, $oUser );
		}
		return $oUser;
	}

	/**
	 * hooked to 'init'
	 */
	public function processUserLoginIntent() {
		if ( $this->userHasPendingLoginIntent() ) {
			$this->printLoginIntentForm();
		}
		else {
			$nIntent = $this->getUserLoginIntent();
			if ( $nIntent > 0 ) { // there was an old login intent
				$this->loadWpUsersProcessor()->logoutUser(); // clears the login and login intent
				$this->loadWpFunctionsProcessor()->redirectHere();
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function userHasPendingLoginIntent() {
		return $this->getUserLoginIntent() > $this->time();
	}

	/**
	 * @return int
	 */
	protected function getUserLoginIntent() {
		$nIntent = $this->loadWpUsersProcessor()->getUserMeta( 'login_intent' );
		return ( empty( $nIntent ) || !is_numeric( $nIntent ) ) ? 0 : (int)$nIntent;
	}

	public function printLoginIntentForm() {
		?>
		<html>
		<head>
		</head>
		<body style="height: 20%;
		width: auto;
		margin: 35% auto 0;
		text-align: center;">
		<form action="#" method="post">
			<label>
				<input type="text" name="login_intent_value" />
				<br />Please enter one of the codes from your 2-Factor Device
			</label>
		</form>
		</body>
		</html>
		<?php
		die();
	}

	/**
	 * do we even need the flag?
	 */
	public function printLoginIntentFlag_Action() {
		echo sprintf( '<input type="hidden" name="shield-login-intent-flag" value="%s"/>',
			$this->getController()->getSessionId()
		);
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorTwoFactor() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_twofactorauth.php' );
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oProc = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_yubikey.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeatureOptions() );
		return $oProc->setLoginTrack( $this->getLoginTrack() );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator
	 */
	protected function getProcessorGoogleAuthenticator() {
		require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_googleauthenticator.php' );
		$oProc = new ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator( $this->getFeatureOptions() );
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
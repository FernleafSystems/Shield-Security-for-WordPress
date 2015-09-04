<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_V6', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_V6 extends ICWP_WPSF_Processor_Base {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected $oProcessorGasp;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected $oProcessorWpLogin;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected $oProcessorCooldown;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected $oProcessorTwoFactor;

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected $oProcessorYubikey;

	/**
	 * @return bool|void
	 */
	public function getIsLogging() {
		return $this->getIsOption( 'enable_login_protect_log', 'Y' );
	}

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oWp = $this->loadWpFunctionsProcessor();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		// check for remote posting before anything else.
		if ( $this->getIsOption( 'enable_prevent_remote_post', 'Y' ) && ( $oWp->getIsLoginRequest() || $oWp->getIsRegisterRequest() ) ) {
			add_filter( 'authenticate', array( $this, 'checkRemotePostLogin_Filter' ), 9, 2 );
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption( 'enable_login_gasp_check', 'Y' ) ) {
			$this->getProcessorGasp()->run();
		}

		if ( $oFO->getIsCustomLoginPathEnabled() ) {
			$this->getProcessorWpLogin()->run();
		}

		if ( $this->getOption( 'login_limit_interval' ) > 0 && ( $oWp->getIsLoginRequest() || $oWp->getIsRegisterRequest() ) ) {
			$this->getProcessorCooldown()->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			$this->getProcessorYubikey()->run();
		}

		if ( $oFO->getIsEmailTwoFactorAuthEnabled() ) {
			$this->getProcessorTwoFactor()->run();
		}

		add_action( 'wp_login_failed', array( $this, 'blackMarkFailedLogin' ), 10, 0 );

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
		return true;
	}

	public function addToAdminNotices() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( $oFO->getIsTwoFactorAuthOn() && !$oFO->getIsEmailTwoFactorAuthEnabled() ) {
			add_filter( $oFO->doPluginPrefix( 'generate_admin_notices' ), array( $this, 'adminNoticeVerifyEmailAbility' ) );
		}
	}

	public function blackMarkFailedLogin() {
		add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
	}

	/**
	 */
	public function adminNoticeVerifyEmailAbility() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( $oFO->getIsTwoFactorAuthOn() && !$oFO->getIfCanSendEmail() ) {

			$aDisplayData = array(
				'render-slug' => 'email-verification-sent',
				'strings' => array(
					'need_you_confirm' => _wpsf__("Before completing activation of email-based two-factor authentication we need you to confirm your site can send emails."),
					'please_click_link' => _wpsf__("Please click the link in the email you received."),
					'email_sent_to' => sprintf( _wpsf__("The email has been sent to you at blog admin address: %s"), get_bloginfo('admin_email') ),
					'how_resend_email' => _wpsf__("To have this email resent, re-save your Login Protection settings."),
					'how_turn_off' => _wpsf__("To turn this notice off, disable Two Factor authentication."),
				)
			);
			$this->insertAdminNotice( $aDisplayData );
		}
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$oDp = $this->loadDataProcessor();
		$sForceLogout = $oDp->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 6 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your Two-Factor Authentication was un-verified or invalidated by a login from another location or browser.').'<br />'._wpsf__('Please login again.') );
		}
		return $oError;
	}

	/**
	 * @param WP_User|WP_Error $oUserOrError
	 * @param string $sUsername
	 * @return mixed
	 */
	public function checkRemotePostLogin_Filter( $oUserOrError, $sUsername ) {
		$sHttpRef = $this->loadDataProcessor()->FetchServer( 'HTTP_REFERER' );

		if ( !empty( $sHttpRef ) ) {
			$aHttpRefererParts = parse_url( $sHttpRef );
			$aHomeUrlParts = parse_url( $this->loadWpFunctionsProcessor()->getHomeUrl() );

			if ( !empty( $aHttpRefererParts['host'] ) && !empty( $aHomeUrlParts['host'] ) && ( $aHttpRefererParts['host'] === $aHomeUrlParts['host'] ) ) {
				$this->doStatIncrement( 'login.remotepost.success' );
				return $oUserOrError;
			}
		}

		$this->doStatIncrement( 'login.remotepost.fail' );
		$sAuditMessage = sprintf(
			_wpsf__( 'Blocked remote %s attempt by user "%s", where HTTP_REFERER was "%s".' ),
			$this->loadWpFunctionsProcessor()->getIsLoginRequest() ? _wpsf__('login') : _wpsf__('register'),
			$sUsername,
			$sHttpRef
		);
		$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_block_remote' );

		$this->loadWpFunctionsProcessor()->wpDie(
			_wpsf__( 'Sorry, you must login directly from within the site.' )
			.' '._wpsf__( 'Remote login is not supported.' )
			.'<br /><a href="http://icwp.io/4n" target="_blank">&rarr;'._wpsf__('More Info').'</a>'
		);
		return $oUserOrError;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	protected function getProcessorCooldown() {
		if ( !isset( $this->oProcessorCooldown ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_cooldown.php' );
			$this->oProcessorCooldown = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->getFeatureOptions() );
		}
		return $this->oProcessorCooldown;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
	 */
	protected function getProcessorTwoFactor() {
		if ( !isset( $this->oProcessorTwoFactor ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_twofactorauth.php' );
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeatureOptions();
			$this->oProcessorTwoFactor = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $oFO );
		}
		return $this->oProcessorTwoFactor;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	protected function getProcessorGasp() {
		if ( !isset( $this->oProcessorGasp ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_gasp.php' );
			$this->oProcessorGasp = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->getFeatureOptions() );
		}
		return $this->oProcessorGasp;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	protected function getProcessorWpLogin() {
		if ( !isset( $this->oProcessorWpLogin ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_wplogin.php' );
			$this->oProcessorWpLogin = new ICWP_WPSF_Processor_LoginProtect_WpLogin( $this->getFeatureOptions() );
		}
		return $this->oProcessorWpLogin;
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Yubikey
	 */
	protected function getProcessorYubikey() {
		if ( !isset( $this->oProcessorYubikey ) ) {
			require_once( dirname(__FILE__).ICWP_DS.'loginprotect_yubikey.php' );
			$this->oProcessorYubikey = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->getFeatureOptions() );
		}
		return $this->oProcessorYubikey;
	}
}
endif;

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect', false ) ):
	class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_LoginProtect_V6 { }
endif;
<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var LoginGuard\Lib\TwoFactor\MfaController
	 */
	private $oLoginIntentController;

	protected function preProcessOptions() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		/**
		 * $oWp = $this->loadWpFunctionsProcessor();
		 * $sCustomLoginPath = $this->cleanLoginUrlPath();
		 * if ( !empty( $sCustomLoginPath ) && $oWp->getIsPermalinksEnabled() ) {
		 * $oWp->resavePermalinks();
		 * }
		 */
		if ( $this->isModuleOptionsRequest() && $oOpts->isEnabledEmailAuth() && !$oOpts->getIfCanSendEmailVerified() ) {
			$this->setIfCanSendEmail( false )
				 ->sendEmailVerifyCanSend();
		}

		$aIds = $oOpts->getOpt( 'antibot_form_ids', [] );
		foreach ( $aIds as $nKey => $sId ) {
			$sId = trim( strip_tags( $sId ) );
			if ( empty( $sId ) ) {
				unset( $aIds[ $nKey ] );
			}
			else {
				$aIds[ $nKey ] = $sId;
			}
		}
		$oOpts->setOpt( 'antibot_form_ids', array_values( array_unique( $aIds ) ) );

		$this->cleanLoginUrlPath();
		$this->ensureCorrectCaptchaConfig();
	}

	public function ensureCorrectCaptchaConfig() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$sStyle = $oOpts->getOpt( 'enable_google_recaptcha_login' );
		if ( $this->isPremium() ) {
			$oCfg = $this->getCaptchaCfg();
			if ( $oCfg->provider == $oCfg::PROV_GOOGLE_RECAP2 ) {
				if ( !$oCfg->invisible && $sStyle == 'invisible' ) {
					$oOpts->setOpt( 'enable_google_recaptcha_login', 'default' );
				}
			}
		}
		elseif ( !in_array( $sStyle, [ 'disabled', 'default' ] ) ) {
			$oOpts->setOpt( 'enable_google_recaptcha_login', 'default' );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function handleModAction( $sAction ) {
		switch ( $sAction ) {
			case 'email_send_verify':
				$this->processEmailSendVerify();
				break;
			default:
				break;
		}
	}

	/**
	 * @uses wp_redirect()
	 */
	private function processEmailSendVerify() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$this->setIfCanSendEmail( true );
		$this->saveModOptions();

		if ( $oOpts->getIfCanSendEmailVerified() ) {
			$bSuccess = true;
			$sMessage = __( 'Email verification completed successfully.', 'wp-simple-firewall' );
		}
		else {
			$bSuccess = false;
			$sMessage = __( 'Email verification could not be completed.', 'wp-simple-firewall' );
		}
		$this->setFlashAdminNotice( $sMessage, !$bSuccess );
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	/**
	 * @param string $sEmail
	 * @param bool   $bSendAsLink
	 * @return bool
	 */
	public function sendEmailVerifyCanSend( $sEmail = null, $bSendAsLink = true ) {

		if ( !Services::Data()->validEmail( $sEmail ) ) {
			$sEmail = get_bloginfo( 'admin_email' );
		}

		$aMessage = [
			__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.', 'wp-simple-firewall' ),
			__( 'This verifies your website can send email and that your account can receive emails sent from your site.', 'wp-simple-firewall' ),
			''
		];

		if ( $bSendAsLink ) {
			$aMessage[] = sprintf(
				__( 'Click the verify link: %s', 'wp-simple-firewall' ),
				add_query_arg( $this->getModActionParams( 'email_send_verify' ), Services::WpGeneral()->getHomeUrl() )
			);
		}
		else {
			$aMessage[] = sprintf( __( "Here's your code for the guided wizard: %s", 'wp-simple-firewall' ), $this->getCanEmailVerifyCode() );
		}

		$sEmailSubject = __( 'Email Sending Verification', 'wp-simple-firewall' );
		return $this->getEmailProcessor()
					->sendEmailWithWrap( $sEmail, $sEmailSubject, $aMessage );
	}

	private function cleanLoginUrlPath() {
		$sCustomLoginPath = $this->getCustomLoginPath();
		if ( !empty( $sCustomLoginPath ) ) {
			$sCustomLoginPath = preg_replace( '#[^0-9a-zA-Z-]#', '', trim( $sCustomLoginPath, '/' ) );
			$this->setOpt( 'rename_wplogin_path', $sCustomLoginPath );
		}
	}

	/**
	 * @param bool $bAsOptDefaults
	 * @return array
	 */
	public function getOptEmailTwoFactorRolesDefaults( $bAsOptDefaults = true ) {
		$aTwoAuthRoles = [
			'type' => 'multiple_select',
			0      => __( 'Subscribers', 'wp-simple-firewall' ),
			1      => __( 'Contributors', 'wp-simple-firewall' ),
			2      => __( 'Authors', 'wp-simple-firewall' ),
			3      => __( 'Editors', 'wp-simple-firewall' ),
			8      => __( 'Administrators', 'wp-simple-firewall' )
		];
		if ( $bAsOptDefaults ) {
			unset( $aTwoAuthRoles[ 'type' ] );
			unset( $aTwoAuthRoles[ 0 ] );
			return array_keys( $aTwoAuthRoles );
		}
		return $aTwoAuthRoles;
	}

	/**
	 * @return string
	 */
	public function getCustomLoginPath() {
		return $this->getOpt( 'rename_wplogin_path', '' );
	}

	/**
	 * @return bool
	 */
	public function isCustomLoginPathEnabled() {
		$sPath = $this->getCustomLoginPath();
		return !empty( $sPath );
	}

	/**
	 * @return string
	 */
	public function getGaspKey() {
		$sKey = $this->getOpt( 'gasp_key' );
		if ( empty( $sKey ) ) {
			$sKey = uniqid();
			$this->setOpt( 'gasp_key', $sKey );
		}
		return $this->prefix( $sKey );
	}

	/**
	 * @return string
	 */
	public function getTextImAHuman() {
		return stripslashes( $this->getTextOpt( 'text_imahuman' ) );
	}

	/**
	 * @return string
	 */
	public function getTextPleaseCheckBox() {
		return stripslashes( $this->getTextOpt( 'text_pleasecheckbox' ) );
	}

	/**
	 * @return string
	 */
	public function getCanEmailVerifyCode() {
		return strtoupper( substr( $this->getCon()->getSiteInstallationId(), 10, 6 ) );
	}

	/**
	 * @return bool
	 */
	public function isEnabledCaptcha() {
		return !$this->isOpt( 'enable_google_recaptcha_login', 'disabled' ) && $this->getCaptchaCfg()->ready;
	}

	/**
	 * @return Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO
	 */
	public function getCaptchaCfg() {
		$oCfg = parent::getCaptchaCfg();
		$sStyle = $this->getOpt( 'enable_google_recaptcha_login' );
		if ( $sStyle !== 'default' && $this->isPremium() ) {
			$oCfg->theme = $sStyle;
			$oCfg->invisible = $oCfg->theme == 'invisible';
		}
		return $oCfg;
	}

	/**
	 * @return LoginGuard\Lib\TwoFactor\MfaController
	 */
	public function getLoginIntentController() {
		if ( !isset( $this->oLoginIntentController ) ) {
			$this->oLoginIntentController = ( new LoginGuard\Lib\TwoFactor\MfaController() )
				->setMod( $this );
		}
		return $this->oLoginIntentController;
	}

	/**
	 * @param bool $bIsChained
	 * @return $this
	 */
	public function setIsChainedAuth( $bIsChained ) {
		return $this->setOpt( 'enable_chained_authentication', $bIsChained ? 'Y' : 'N' );
	}

	/**
	 * @param bool $bCan
	 * @return $this
	 */
	public function setIfCanSendEmail( $bCan ) {
		return $this->setOpt( 'email_can_send_verified_at', $bCan ? Services::Request()->ts() : 0 );
	}

	/**
	 * @param bool $bCan
	 * @return $this
	 */
	public function setEnabled2FaEmail( $bCan ) {
		return $this->setOpt( 'enable_email_authentication', $bCan ? 'Y' : 'N' );
	}

	/**
	 * @param bool $bCan
	 * @return $this
	 */
	public function setEnabled2FaGoogleAuthenticator( $bCan ) {
		return $this->setOpt( 'enable_google_authenticator', $bCan ? 'Y' : 'N' );
	}

	/**
	 * @return string
	 */
	public function getLoginIntentRequestFlag() {
		return $this->prefix( 'login-intent-request' );
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {
			case 'text_imahuman':
				$sText = __( "I'm a human.", 'wp-simple-firewall' );
				break;

			case 'text_pleasecheckbox':
				$sText = __( "Please check the box to show us you're a human.", 'wp-simple-firewall' );
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setEnabledGaspCheck( $bEnabled = true ) {
		return $this->setOpt( 'enable_login_gasp_check', $bEnabled ? 'Y' : 'N' );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		wp_localize_script(
			$this->prefix( 'global-plugin' ),
			'icwp_wpsf_vars_lg',
			[
				'ajax_gen_backup_codes' => $this->getAjaxActionData( 'gen_backup_codes' ),
				'ajax_del_backup_codes' => $this->getAjaxActionData( 'del_backup_codes' ),
			]
		);
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'LoginGuard';
	}
}
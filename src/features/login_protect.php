<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 */
	public function getIfUseLoginIntentPage() {
		return $this->isOpt( 'use_login_intent_page', true );
	}

	protected function doExtraSubmitProcessing() {
		/**
		 * $oWp = $this->loadWpFunctionsProcessor();
		 * $sCustomLoginPath = $this->cleanLoginUrlPath();
		 * if ( !empty( $sCustomLoginPath ) && $oWp->getIsPermalinksEnabled() ) {
		 * $oWp->resavePermalinks();
		 * }
		 */
		if ( $this->isModuleOptionsRequest() && $this->isEmailAuthenticationOptionOn() && !$this->getIfCanSendEmailVerified() ) {
			$this->setIfCanSendEmail( false )
				 ->sendEmailVerifyCanSend();
		}

		$aIds = $this->getAntiBotFormSelectors();
		foreach ( $aIds as $nKey => $sId ) {
			$sId = trim( strip_tags( $sId ) );
			if ( empty( $sId ) ) {
				unset( $aIds[ $nKey ] );
			}
			else {
				$aIds[ $nKey ] = $sId;
			}
		}
		$this->setOpt( 'antibot_form_ids', array_values( array_unique( $aIds ) ) );

		$this->cleanLoginUrlPath();
	}

	/**
	 */
	public function handleModRequest() {
		switch ( Services::Request()->query( 'exec' ) ) {
			case 'email_send_verify':
				$this->processEmailSendVerify();
				break;
			default:
				break;
		}
	}

	/**
	 * @return string
	 */
	private function generateCanSendEmailVerifyLink() {
		return add_query_arg( $this->getNonceActionData( 'email_send_verify' ), $this->getUrl_AdminPage() );
	}

	/**
	 * @uses wp_redirect()
	 */
	private function processEmailSendVerify() {
		$this->setIfCanSendEmail( true )
			 ->savePluginOptions();

		if ( $this->getIfCanSendEmailVerified() ) {
			$bSuccess = true;
			$sMessage = __( 'Email verification completed successfully.', 'wp-simple-firewall' );
		}
		else {
			$bSuccess = false;
			$sMessage = __( 'Email verification could not be completed.', 'wp-simple-firewall' );
		}
		$this->setFlashAdminNotice( $sMessage, !$bSuccess );
		Services::Response()->redirect( $this->getUrl_AdminPage() );
	}

	/**
	 * @param string $sEmail
	 * @param bool   $bSendAsLink
	 * @return boolean
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
			$aMessage[] = sprintf( __( 'Click the verify link: %s', 'wp-simple-firewall' ), $this->generateCanSendEmailVerifyLink() );
		}
		else {
			$aMessage[] = sprintf( __( "Here's your code for the guided wizard: %s", 'wp-simple-firewall' ), $this->getCanEmailVerifyCode() );
		}

		$sEmailSubject = __( 'Email Sending Verification', 'wp-simple-firewall' );
		return $this->getEmailProcessor()
					->sendEmailWithWrap( $sEmail, $sEmailSubject, $aMessage );
	}

	/**
	 */
	private function cleanLoginUrlPath() {
		$sCustomLoginPath = $this->getCustomLoginPath();
		if ( !empty( $sCustomLoginPath ) ) {
			$sCustomLoginPath = preg_replace( '#[^0-9a-zA-Z-]#', '', trim( $sCustomLoginPath, '/' ) );
			$this->setOpt( 'rename_wplogin_path', $sCustomLoginPath );
		}
	}

	/**
	 * @return bool
	 */
	public function isProtectLogin() {
		return $this->isProtect( 'login' );
	}

	/**
	 * @return bool
	 */
	public function isProtectLostPassword() {
		return $this->isProtect( 'password' );
	}

	/**
	 * @return bool
	 */
	public function isProtectRegister() {
		return $this->isProtect( 'register' );
	}

	/**
	 * @param string $sLocationKey - see config for keys, e.g. login, register, password, checkout_woo
	 * @return bool
	 */
	public function isProtect( $sLocationKey ) {
		return in_array( $sLocationKey, $this->getBotProtectionLocations() );
	}

	/**
	 * @return array
	 */
	public function getEmail2FaRoles() {
		$aRoles = $this->getOpt( 'two_factor_auth_user_roles', [] );
		if ( empty( $aRoles ) || !is_array( $aRoles ) ) {
			$aRoles = $this->getOptEmailTwoFactorRolesDefaults();
			$this->setOpt( 'two_factor_auth_user_roles', $aRoles );
		}
		if ( $this->isPremium() ) {
			$aRoles = apply_filters( 'odp-shield-2fa_email_user_roles', $aRoles );
		}
		return is_array( $aRoles ) ? $aRoles : $this->getOptEmailTwoFactorRolesDefaults();
	}

	/**
	 * @param boolean $bAsOptDefaults
	 * @return array
	 */
	protected function getOptEmailTwoFactorRolesDefaults( $bAsOptDefaults = true ) {
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
	 * @return int
	 */
	public function getCooldownInterval() {
		return (int)$this->getOpt( 'login_limit_interval' );
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
		return strtoupper( substr( $this->getTwoAuthSecretKey(), 4, 6 ) );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function canUserMfaSkip( $oUser ) {
		$oReq = Services::Request();
		if ( $this->getMfaSkipEnabled() ) {
			$aHashes = $this->getMfaLoginHashes( $oUser );
			$nSkipTime = $this->getMfaSkip()*DAY_IN_SECONDS;

			$sHash = md5( $oReq->getUserAgent() );
			$bCanSkip = isset( $aHashes[ $sHash ] )
						&& ( (int)$aHashes[ $sHash ] + $nSkipTime ) > $oReq->ts();
		}
		else if ( $this->getIfSupport3rdParty() && class_exists( 'WC_Social_Login' ) ) {
			// custom support for WooCommerce Social login
			$oMeta = $this->getCon()->getUserMeta( $oUser );
			$bCanSkip = isset( $oMeta->wc_social_login_valid ) ? $oMeta->wc_social_login_valid : false;
		}
		else {
			/**
			 * TODO: remove the HTTP_REFERER bit once iCWP plugin is updated.
			 * We want logins from iCWP to skip 2FA. To achieve this, iCWP plugin needs
			 * to add a TRUE filter on 'odp-shield-2fa_skip' at the point of login.
			 * Until then, we'll use the HTTP referrer as an indicator
			 */
			$bCanSkip = apply_filters(
				'odp-shield-2fa_skip',
				strpos( $oReq->server( 'HTTP_REFERER' ), 'https://app.icontrolwp.com/' ) === 0
			);
		}
		return $bCanSkip;
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	public function addMfaLoginHash( $oUser ) {
		$oReq = Services::Request();
		$aHashes = $this->getMfaLoginHashes( $oUser );
		$aHashes[ md5( $oReq->getUserAgent() ) ] = $oReq->ts();
		$this->getCon()->getCurrentUserMeta()->hash_loginmfa = $aHashes;
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @return array
	 */
	public function getMfaLoginHashes( $oUser ) {
		$oMeta = $this->getCon()->getUserMeta( $oUser );
		$aHashes = $oMeta->hash_loginmfa;
		if ( !is_array( $aHashes ) ) {
			$aHashes = [];
			$oMeta->hash_loginmfa = $aHashes;
		}
		return $aHashes;
	}

	/**
	 * @return bool
	 */
	public function getMfaSkipEnabled() {
		return $this->getMfaSkip() > 0;
	}

	/**
	 * @return int
	 */
	public function getMfaSkip() {
		return (int)$this->getOpt( 'mfa_skip', 0 );
	}

	/**
	 * @return string
	 */
	public function getTwoAuthSecretKey() {
		$sKey = $this->getOpt( 'two_factor_secret_key' );
		if ( empty( $sKey ) ) {
			$sKey = md5( mt_rand() );
			$this->setOpt( 'two_factor_secret_key', $sKey );
		}
		return $sKey;
	}

	/**
	 * @return bool
	 */
	public function isEmailAuthenticationOptionOn() {
		return $this->isOpt( 'enable_email_authentication', 'Y' );
	}

	/**
	 * Also considers whether email sending ability has been verified
	 * @return bool
	 */
	public function isEmailAuthenticationActive() {
		return $this->getIfCanSendEmailVerified() && $this->isEmailAuthenticationOptionOn();
	}

	/**
	 * @return bool
	 */
	public function isEnabledBackupCodes() {
		return $this->isPremium() && $this->isOpt( 'allow_backupcodes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledGoogleAuthenticator() {
		return $this->isOpt( 'enable_google_authenticator', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isGoogleRecaptchaEnabled() {
		return ( !$this->isOpt( 'enable_google_recaptcha_login', 'disabled' ) && $this->isGoogleRecaptchaReady() );
	}

	/**
	 * @return int
	 */
	public function getCanSendEmailVerifiedAt() {
		return $this->getOpt( 'email_can_send_verified_at' );
	}

	/**
	 * @return bool
	 */
	public function getIfCanSendEmailVerified() {
		return $this->getCanSendEmailVerifiedAt() > 0;
	}

	/**
	 * @return string
	 */
	public function getGoogleRecaptchaStyle() {
		$sStyle = $this->getOpt( 'enable_google_recaptcha_login' );
		if ( $sStyle == 'default' ) {
			$sStyle = parent::getGoogleRecaptchaStyle();
		}
		return $sStyle;
	}

	/**
	 * @return array
	 */
	public function getBotProtectionLocations() {
		$aLocs = $this->getOpt( 'bot_protection_locations' );
		return is_array( $aLocs ) ? $aLocs : (array)$this->getOptionsVo()->getOptDefault( 'bot_protection_locations' );
	}

	/**
	 * @return bool
	 */
	public function isCooldownEnabled() {
		return $this->getCooldownInterval() > 0;
	}

	/**
	 * @return bool
	 */
	public function isChainedAuth() {
		return $this->isOpt( 'enable_chained_authentication', 'Y' );
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
		$nCurrentDateAt = $this->getCanSendEmailVerifiedAt();
		if ( $bCan ) {
			$nDateAt = ( $nCurrentDateAt <= 0 ) ? Services::Request()->ts() : $nCurrentDateAt;
		}
		else {
			$nDateAt = 0;
		}
		return $this->setOpt( 'email_can_send_verified_at', $nDateAt );
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
	 * @return bool
	 */
	public function isEnabledGaspCheck() {
		return $this->isModOptEnabled() && $this->isOpt( 'enable_login_gasp_check', 'Y' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setEnabledGaspCheck( $bEnabled = true ) {
		return $this->setOpt( 'enable_login_gasp_check', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		if ( $sSection == 'section_brute_force_login_protection' && !$this->isPremium() ) {
			$sIntegration = $this->getPremiumOnlyIntegration();
			if ( !empty( $sIntegration ) ) {
				$aWarnings[] = sprintf( __( 'Support for login protection with %s is a Pro-only feature.', 'wp-simple-firewall' ), $sIntegration );
			}
		}

		if ( $sSection == 'section_2fa_email' ) {
			$aWarnings[] =
				__( '2FA by email demands that your WP site is properly configured to send email.', 'wp-simple-firewall' )
				.'<br/>'.__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://icwp.io/dd', __( 'Learn More.', 'wp-simple-firewall' ) );
		}

		return $aWarnings;
	}

	/**
	 * @return string
	 */
	protected function getPremiumOnlyIntegration() {
		$aIntegrations = [
			'WooCommerce'            => 'WooCommerce',
			'Easy_Digital_Downloads' => 'Easy Digital Downloads',
			'BuddyPress'             => 'BuddyPress',
		];

		$sIntegration = '';
		foreach ( $aIntegrations as $sInt => $sName ) {
			if ( class_exists( $sInt ) ) {
				$sIntegration = $sName;
				break;
			}
		}
		return $sIntegration;
	}

	/**
	 * @return bool
	 */
	public function isYubikeyActive() {
		return $this->isOpt( 'enable_yubikey', 'Y' ) && $this->isYubikeyConfigReady();
	}

	/**
	 * @return bool
	 */
	private function isYubikeyConfigReady() {
		$sAppId = $this->getOpt( 'yubikey_app_id' );
		$sApiKey = $this->getOpt( 'yubikey_api_key' );
		return !empty( $sAppId ) && !empty( $sApiKey );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'gen_backup_codes':
					$aAjaxResponse = $this->ajaxExec_GenBackupCodes();
					break;

				case 'del_backup_codes':
					$aAjaxResponse = $this->ajaxExec_DeleteBackupCodes();
					break;

				case 'resend_verification_email':
					$aAjaxResponse = $this->ajaxExec_ResendEmailVerification();
					break;

				case 'disable_2fa_email':
					$aAjaxResponse = $this->ajaxExec_Disable2faEmail();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_GenBackupCodes() {
		/** @var ICWP_WPSF_Processor_LoginProtect $oPro */
		$oPro = $this->loadProcessor();
		$sPass = $oPro->getSubProIntent()
					  ->getProcessorBackupCodes()
					  ->resetSecret( Services::WpUsers()->getCurrentWpUser() );

		foreach ( [ 20, 15, 10, 5 ] as $nPos ) {
			$sPass = substr_replace( $sPass, '-', $nPos, 0 );
		}

		return [
			'code'    => $sPass,
			'success' => true
		];
	}

	/**
	 * @return bool
	 */
	public function isEnabledBotJs() {
		return $this->isPremium() && $this->isOpt( 'enable_antibot_js', 'Y' )
			   && count( $this->getAntiBotFormSelectors() ) > 0
			   && ( $this->isEnabledGaspCheck() || $this->isGoogleRecaptchaEnabled() );
	}

	/**
	 * @return array
	 */
	public function getAntiBotFormSelectors() {
		$aIds = $this->getOpt( 'antibot_form_ids', [] );
		return is_array( $aIds ) ? $aIds : [];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_DeleteBackupCodes() {

		/** @var ICWP_WPSF_Processor_LoginProtect $oPro */
		$oPro = $this->loadProcessor();
		$oPro->getSubProIntent()
			 ->getProcessorBackupCodes()
			 ->deleteSecret( Services::WpUsers()->getCurrentWpUser() );
		$this->setFlashAdminNotice( __( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' ) );
		return [
			'success' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_Disable2faEmail() {
		$this->setEnabled2FaEmail( false );
		return [
			'success'     => true,
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ResendEmailVerification() {
		$bSuccess = true;

		if ( !$this->isEmailAuthenticationOptionOn() ) {
			$sMessage = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
			$bSuccess = false;
		}
		else if ( $this->getIfCanSendEmailVerified() ) {
			$sMessage = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$sMessage = __( 'Verification email resent.', 'wp-simple-firewall' );
			$this->setIfCanSendEmail( false )
				 ->sendEmailVerifyCanSend();
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
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
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'Login Guard', 'wp-simple-firewall' ),
				'sub'   => __( 'Brute Force Protection & Identity Verification', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasBotCheck = $this->isEnabledGaspCheck() || $this->isGoogleRecaptchaEnabled();

			$bBotLogin = $bHasBotCheck && $this->isProtectLogin();
			$bBotRegister = $bHasBotCheck && $this->isProtectRegister();
			$bBotPassword = $bHasBotCheck && $this->isProtectLostPassword();
			$aThis[ 'key_opts' ][ 'bot_login' ] = [
				'name'    => __( 'Brute Force Login', 'wp-simple-firewall' ),
				'enabled' => $bBotLogin,
				'summary' => $bBotLogin ?
					__( 'Login forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Login forms are not protected against brute force bot attacks', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$aThis[ 'key_opts' ][ 'bot_register' ] = [
				'name'    => __( 'Bot User Register', 'wp-simple-firewall' ),
				'enabled' => $bBotRegister,
				'summary' => $bBotRegister ?
					__( 'Registration forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Registration forms are not protected against automated bots', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$aThis[ 'key_opts' ][ 'bot_password' ] = [
				'name'    => __( 'Brute Force Lost Password', 'wp-simple-firewall' ),
				'enabled' => $bBotPassword,
				'summary' => $bBotPassword ?
					__( 'Lost Password forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Lost Password forms are not protected against automated bots', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];

			$bHas2Fa = $this->isEmailAuthenticationActive()
					   || $this->isEnabledGoogleAuthenticator() || $this->isYubikeyActive();
			$aThis[ 'key_opts' ][ '2fa' ] = [
				'name'    => __( 'Identity Verification', 'wp-simple-firewall' ),
				'enabled' => $bHas2Fa,
				'summary' => $bHas2Fa ?
					__( 'At least 1 2FA option is enabled', 'wp-simple-firewall' )
					: __( 'No 2FA options, such as Google Authenticator, are active', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_2fa_email' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_login_protection' :
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sTitleShort = sprintf( __( '%s/%s ', 'wp-simple-firewall' ), __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Login Guard blocks all automated and brute force attempts to log in to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_recaptcha' :
				$sTitle = 'Google reCAPTCHA';
				$sTitleShort = 'reCAPTCHA';
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Adds Google reCAPTCHA to the Login Forms.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this turned on.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( "You will need to register for Google reCAPTCHA keys and store them in the Shield 'Dashboard' settings.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_rename_wplogin' :
				$sTitle = __( 'Hide WordPress Login Page', 'wp-simple-firewall' );
				$sTitleShort = __( 'Hide Login', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_multifactor_authentication' :
				$sTitle = __( 'Multi-Factor Authentication', 'wp-simple-firewall' );
				$sTitleShort = __( 'Multi-Factor Auth', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					__( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' )
				];
				break;

			case 'section_2fa_email' :
				$sTitle = __( 'Email Two-Factor Authentication', 'wp-simple-firewall' );
				$sTitleShort = __( '2FA Email', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using email-based one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_2fa_ga' :
				$sTitle = __( 'Google Authenticator Two-Factor Authentication', 'wp-simple-firewall' );
				$sTitleShort = __( 'Google Auth', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Google Authenticator one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_brute_force_login_protection' :
				$sTitle = __( 'Brute Force Login Protection', 'wp-simple-firewall' );
				$sTitleShort = __( 'Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks brute force hacking attacks against your login and registration pages.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_yubikey_authentication' :
				$sTitle = __( 'Yubikey Two-Factor Authentication', 'wp-simple-firewall' );
				$sTitleShort = __( 'Yubikey', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Yubikey one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				list( $sTitle, $sTitleShort, $aSummary ) = $this->loadStrings_SectionTitlesDefaults( $aOptionsParams );
		}

		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_login_protect' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'rename_wplogin_path' :
				$sName = __( 'Hide WP Login Page', 'wp-simple-firewall' );
				$sSummary = __( 'Hide The WordPress Login Page', 'wp-simple-firewall' );
				$sDescription = __( 'Creating a path here will disable your wp-login.php', 'wp-simple-firewall' )
								.'<br />'
								.sprintf( __( 'Only letters and numbers are permitted: %s', 'wp-simple-firewall' ), '<strong>abc123</strong>' )
								.'<br />'
								.sprintf( __( 'Your current login URL is: %s', 'wp-simple-firewall' ), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' );
				break;

			case 'enable_chained_authentication' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Multi-Factor Authentication', 'wp-simple-firewall' ) );
				$sSummary = __( 'Require All Active Authentication Factors', 'wp-simple-firewall' );
				$sDescription = __( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.', 'wp-simple-firewall' );
				break;

			case 'mfa_skip' :
				$sName = __( 'Multi-Factor By-Pass', 'wp-simple-firewall' );
				$sSummary = __( 'A User Can By-Pass Multi-Factor Authentication (MFA) For The Set Number Of Days', 'wp-simple-firewall' );
				$sDescription = __( 'Enter the number of days a user can by-pass future MFA after a successful MFA-login. 0 to disable.', 'wp-simple-firewall' );
				break;

			case 'allow_backupcodes' :
				$sName = __( 'Allow Backup Codes', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Users To Generate A Backup Code', 'wp-simple-firewall' );
				$sDescription = __( 'Allow users to generate a backup code that can be used to login if MFA factors are unavailable.', 'wp-simple-firewall' );
				break;

			case 'enable_google_authenticator' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) );
				$sSummary = __( 'Allow Users To Use Google Authenticator', 'wp-simple-firewall' );
				$sDescription = __( 'When enabled, users will have the option to add Google Authenticator to their WordPress user profile', 'wp-simple-firewall' );
				break;

			case 'enable_email_authentication' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$sSummary = sprintf( __( 'Two-Factor Login Authentication By %s', 'wp-simple-firewall' ), __( 'Email', 'wp-simple-firewall' ) );
				$sDescription = __( 'All users will be required to verify their login by email-based two-factor authentication.', 'wp-simple-firewall' );
				break;

			case 'two_factor_auth_user_roles' :
				$sName = sprintf( '%s - %s', __( 'Enforce', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$sSummary = __( 'All User Roles Subject To Email Authentication', 'wp-simple-firewall' );
				$sDescription = __( 'Enforces email-based authentication on all users with the selected roles.', 'wp-simple-firewall' )
								.'<br /><strong>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'This setting only applies to %s.', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) ) ).'</strong>';
				break;

			case 'enable_google_recaptcha_login' :
				$sName = __( 'Google reCAPTCHA', 'wp-simple-firewall' );
				$sSummary = __( 'Protect WordPress Account Access Requests With Google reCAPTCHA', 'wp-simple-firewall' );
				$sDescription = __( 'Use Google reCAPTCHA on the user account forms such as login, register, etc.', 'wp-simple-firewall' ).'<br />'
								.sprintf( __( 'Use of any theme other than "%s", requires a Pro license.', 'wp-simple-firewall' ), __( 'Light Theme', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( "You'll need to setup your Google reCAPTCHA API Keys in 'General' settings.", 'wp-simple-firewall' ) )
								.'<br/><strong>'.sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( "Some forms are more dynamic than others so if you experience problems, please use non-Invisible reCAPTCHA.", 'wp-simple-firewall' ) ).'</strong>';
				break;

			case 'google_recaptcha_style_login' : // Unused
				$sName = __( 'reCAPTCHA Style', 'wp-simple-firewall' );
				$sSummary = __( 'How Google reCAPTCHA Will Be Displayed', 'wp-simple-firewall' );
				$sDescription = __( 'You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha', 'wp-simple-firewall' );
				break;

			case 'bot_protection_locations' :
				$sName = __( 'Protection Locations', 'wp-simple-firewall' );
				$sSummary = __( 'Which Forms Should Be Protected', 'wp-simple-firewall' );
				$sDescription = __( 'Choose the forms for which bot protection measures will be deployed.', 'wp-simple-firewall' ).'<br />'
								.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( "Use with 3rd party systems such as %s, requires a Pro license.", 'wp-simple-firewall' ), 'WooCommerce' ) );
				break;

			case 'enable_login_gasp_check' :
				$sName = __( 'Bot Protection', 'wp-simple-firewall' );
				$sSummary = __( 'Protect WP Login From Automated Login Attempts By Bots', 'wp-simple-firewall' );
				$sDescription = __( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'ON', 'wp-simple-firewall' ) );
				break;

			case 'enable_antibot_js' :
				$sName = __( 'AntiBot JS', 'wp-simple-firewall' );
				$sSummary = __( 'Use AntiBot JS Includes For Custom 3rd Party Forms', 'wp-simple-firewall' );
				$sDescription = __( 'Important: This is experimental. Please contact support for further assistance.', 'wp-simple-firewall' );
				break;

			case 'antibot_form_ids' :
				$sName = __( 'AntiBot Forms', 'wp-simple-firewall' );
				$sSummary = __( 'Enter The Selectors Of The 3rd Party Login Forms For Use With AntiBot JS', 'wp-simple-firewall' );
				$sDescription = __( 'For use with the AntiBot JS option.', 'wp-simple-firewall' )
								.' '.__( 'IDs are prefixed with "#".', 'wp-simple-firewall' )
								.' '.__( 'Classes are prefixed with ".".', 'wp-simple-firewall' )
								.'<br />'.__( 'IDs are preferred over classes.', 'wp-simple-firewall' );
				break;

			case 'login_limit_interval' :
				$sName = __( 'Cooldown Period', 'wp-simple-firewall' );
				$sSummary = __( 'Limit account access requests to every X seconds', 'wp-simple-firewall' );
				$sDescription = __( 'WordPress will process only ONE account access attempt per number of seconds specified.', 'wp-simple-firewall' )
								.'<br />'.__( 'Zero (0) turns this off.', 'wp-simple-firewall' )
								.' '.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $this->getOptionsVo()
																									->getOptDefault( 'login_limit_interval' ) );
				break;

			case 'enable_user_register_checking' :
				$sName = __( 'User Registration', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Brute Force Protection To User Registration And Lost Passwords', 'wp-simple-firewall' );
				$sDescription = __( 'When enabled, settings in this section will also apply to new user registration and users trying to reset passwords.', 'wp-simple-firewall' );
				break;

			case 'enable_yubikey' :
				$sName = __( 'Enable Yubikey Authentication', 'wp-simple-firewall' );
				$sSummary = __( 'Turn On / Off Yubikey Authentication On This Site', 'wp-simple-firewall' );
				$sDescription = __( 'Combined with your Yubikey API details this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' );
				break;

			case 'yubikey_app_id' :
				$sName = __( 'Yubikey App ID', 'wp-simple-firewall' );
				$sSummary = __( 'Your Unique Yubikey App ID', 'wp-simple-firewall' );
				$sDescription = __( 'Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' )
								.__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.', 'wp-simple-firewall' );
				break;

			case 'yubikey_api_key' :
				$sName = __( 'Yubikey API Key', 'wp-simple-firewall' );
				$sSummary = __( 'Your Unique Yubikey App API Key', 'wp-simple-firewall' );
				$sDescription = __( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.', 'wp-simple-firewall' )
								.__( 'Please review the info link on how to get your own Yubikey App ID and API Key.', 'wp-simple-firewall' );
				break;

			case 'yubikey_unique_keys' :
				$sName = __( 'Yubikey Unique Keys', 'wp-simple-firewall' );
				$sSummary = __( 'This method for Yubikeys is no longer supported. Please see your user profile', 'wp-simple-firewall' );
				$sDescription = '<strong>'.sprintf( '%s: %s', __( 'Format', 'wp-simple-firewall' ), 'Username,Yubikey' ).'</strong>'
								.'<br />- '.__( 'Provide Username<->Yubikey Pairs that are usable for this site.', 'wp-simple-firewall' )
								.'<br />- '.__( 'If a Username if not assigned a Yubikey, Yubikey Authentication is OFF for that user.', 'wp-simple-firewall' )
								.'<br />- '.__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.', 'wp-simple-firewall' );
				break;

			case 'text_imahuman' :
				$sName = __( 'GASP Checkbox Text', 'wp-simple-firewall' );
				$sSummary = __( 'The User Message Displayed Next To The GASP Checkbox', 'wp-simple-firewall' );
				$sDescription = __( "You can change the text displayed to the user beside the checkbox if you need a custom message.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $this->getTextOptDefault( 'text_imahuman' ) );
				break;

			case 'text_pleasecheckbox' :
				$sName = __( 'GASP Alert Text', 'wp-simple-firewall' );
				$sSummary = __( "The Message Displayed If The User Doesn't Check The Box", 'wp-simple-firewall' );
				$sDescription = __( "You can change the text displayed to the user in the alert message if they don't check the box.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $this->getTextOptDefault( 'text_pleasecheckbox' ) );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}
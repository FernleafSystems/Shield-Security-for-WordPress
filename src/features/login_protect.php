<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return bool
	 * @deprecated 8.6.0
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
	 * @uses wp_redirect()
	 */
	private function processEmailSendVerify() {
		$this->setIfCanSendEmail( true );
		$this->saveModOptions();

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
				$this->buildAdminActionNonceUrl( 'email_send_verify' )
			);
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
		return strtoupper( substr( $this->getTwoAuthSecretKey(), 10, 6 ) );
	}

	/**
	 * @param \WP_User $oUser
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
		elseif ( $this->getIfSupport3rdParty() && class_exists( 'WC_Social_Login' ) ) {
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
	 * NOTE: DO NOT REPLACE WITH OPTIONS USE AS THIS RETURNS DAYS
	 * @return int - days
	 * @deprecated 8.6.0
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
	 * @deprecated 8.6.0
	 */
	public function isEmailAuthenticationOptionOn() {
		return $this->isOpt( 'enable_email_authentication', 'Y' );
	}

	/**
	 * Also considers whether email sending ability has been verified
	 * @return bool
	 * @deprecated 8.6.0
	 */
	public function isEmailAuthenticationActive() {
		return $this->getIfCanSendEmailVerified() && $this->isEmailAuthenticationOptionOn();
	}

	/**
	 * @return bool
	 * @deprecated 8.6.0
	 */
	public function isEnabledBackupCodes() {
		return $this->isPremium() && $this->isOpt( 'allow_backupcodes', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 8.6.0
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
	 * @deprecated 8.6.0
	 */
	public function getCanSendEmailVerifiedAt() {
		return $this->getOpt( 'email_can_send_verified_at' );
	}

	/**
	 * @return bool
	 * @deprecated 8.6.0
	 */
	public function getIfCanSendEmailVerified() {
		return $this->getCanSendEmailVerifiedAt() > 0;
	}

	/**
	 * @return string
	 */
	public function getGoogleRecaptchaStyle() {
		$sStyle = $this->getOpt( 'enable_google_recaptcha_login' );
		$aConfig = $this->getGoogleRecaptchaConfig();
		if ( $aConfig[ 'style_override' ] || $sStyle == 'default' ) {
			$sStyle = $aConfig[ 'style' ];
		}
		return $sStyle;
	}

	/**
	 * @return array
	 */
	public function getBotProtectionLocations() {
		$aLocs = $this->getOpt( 'bot_protection_locations' );
		return is_array( $aLocs ) ? $aLocs : (array)$this->getOptions()->getOptDefault( 'bot_protection_locations' );
	}

	/**
	 * @return bool
	 * @deprecated 8.6.0
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
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', __( 'Learn More.', 'wp-simple-firewall' ) );
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
	 * @deprecated 8.6.0
	 */
	public function isYubikeyActive() {
		return $this->isOpt( 'enable_yubikey', 'Y' ) && $this->isYubikeyConfigReady();
	}

	/**
	 * @return bool
	 * @deprecated 8.6.0
	 */
	private function isYubikeyConfigReady() {
		$sAppId = $this->getOpt( 'yubikey_app_id' );
		$sApiKey = $this->getOpt( 'yubikey_api_key' );
		return !empty( $sAppId ) && !empty( $sApiKey );
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'LoginGuard';
	}
}
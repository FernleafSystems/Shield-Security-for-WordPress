<?php

class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		parent::onWpInit();

		$oReq = $this->loadRequest();
		// User has clicked a link in their email to verify they can send email.
		if ( $oReq->query( 'shield_action' ) == 'emailsendverify' ) {
			if ( $oReq->query( 'authkey' ) == $this->getCanEmailVerifyCode() ) {
				$this->setIfCanSendEmail( true )
					 ->savePluginOptions();

				if ( $this->getIfCanSendEmailVerified() ) {
					$this->setFlashAdminNotice( _wpsf__( 'Email verification completed successfully.' ) );
				}
				else {
					$this->setFlashAdminNotice( _wpsf__( 'Email verification could not be completed.' ), true );
				}

				$this->loadWp()->doRedirect( $this->getUrl_AdminPage() );
			}
		}
	}

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

		if ( $this->getOpt( 'login_limit_interval' ) < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'login_limit_interval' );
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
	 * @return string
	 */
	protected function generateCanSendEmailVerifyLink() {
		$aQueryArgs = array(
			'authkey'       => $this->getCanEmailVerifyCode(),
			'shield_action' => 'emailsendverify'
		);
		return add_query_arg( $aQueryArgs, $this->loadWp()->getHomeUrl() );
	}

	/**
	 * @param string $sEmail
	 * @param bool   $bSendAsLink
	 * @return boolean
	 */
	public function sendEmailVerifyCanSend( $sEmail = null, $bSendAsLink = true ) {

		if ( !$this->loadDP()->validEmail( $sEmail ) ) {
			$sEmail = get_bloginfo( 'admin_email' );
		}

		$aMessage = array(
			_wpsf__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.' ),
			_wpsf__( 'This verifies your website can send email and that your account can receive emails sent from your site.' ),
			''
		);

		if ( $bSendAsLink ) {
			$aMessage[] = sprintf( _wpsf__( 'Click the verify link: %s' ), $this->generateCanSendEmailVerifyLink() );
		}
		else {
			$aMessage[] = sprintf( _wpsf__( "Here's your code for the guided wizard: %s" ), $this->getCanEmailVerifyCode() );
		}

		$sEmailSubject = _wpsf__( 'Email Sending Verification' );
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
		$aRoles = $this->getOpt( 'two_factor_auth_user_roles', array() );
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
		$aTwoAuthRoles = array(
			'type' => 'multiple_select',
			0      => _wpsf__( 'Subscribers' ),
			1      => _wpsf__( 'Contributors' ),
			2      => _wpsf__( 'Authors' ),
			3      => _wpsf__( 'Editors' ),
			8      => _wpsf__( 'Administrators' )
		);
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
		return strtoupper( substr( $this->getTwoAuthSecretKey(), 4, 6 ) );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function canUserMfaSkip( $oUser ) {

		if ( $this->getMfaSkipEnabled() ) {
			$aHashes = $this->getMfaLoginHashes( $oUser );
			$nSkipTime = $this->getMfaSkip()*DAY_IN_SECONDS;

			$sHash = md5( $this->loadRequest()->getUserAgent() );
			$bCanSkip = isset( $aHashes[ $sHash ] )
						&& ( (int)$aHashes[ $sHash ] + $nSkipTime ) > $this->loadRequest()->ts();
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
				strpos( $this->loadRequest()->server( 'HTTP_REFERER' ), 'https://app.icontrolwp.com/' ) === 0
			);
		}
		return $bCanSkip;
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	public function addMfaLoginHash( $oUser ) {
		$oReq = $this->loadRequest();
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
			$aHashes = array();
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
		return (int)$this->getOpt( 'login_limit_interval' ) > 0;
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
			$nDateAt = ( $nCurrentDateAt <= 0 ) ? $this->loadRequest()->ts() : $nCurrentDateAt;
		}
		else {
			$nDateAt = 0;
		}
		$this->setOpt( 'email_can_send_verified_at', $nDateAt );
		return $this;
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
				$sText = _wpsf__( "I'm a human." );
				break;

			case 'text_pleasecheckbox':
				$sText = _wpsf__( "Please check the box to show us you're a human." );
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
		$aWarnings = array();

		if ( $sSection == 'section_brute_force_login_protection' && !$this->isPremium() ) {
			$sIntegration = $this->getPremiumOnlyIntegration();
			if ( !empty( $sIntegration ) ) {
				$aWarnings[] = sprintf( _wpsf__( 'Support for login protection with %s is a Pro-only feature.' ), $sIntegration );
			}
		}

		if ( $sSection == 'section_2fa_email' ) {
			$aWarnings[] =
				_wpsf__( '2FA by email demands that your WP site is properly configured to send email.' )
				.'<br/>'._wpsf__( 'This is a common problem and you may get locked out in the future if you ignore this.' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://icwp.io/dd', _wpsf__( 'Learn More.' ) );
		}

		return $aWarnings;
	}

	/**
	 * @return string
	 */
	protected function getPremiumOnlyIntegration() {
		$aIntegrations = array(
			'WooCommerce'            => 'WooCommerce',
			'Easy_Digital_Downloads' => 'Easy Digital Downloads',
			'BuddyPress'             => 'BuddyPress',
		);

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
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'gen_backup_codes':
					$aAjaxResponse = $this->ajaxExec_GenBackupCodes();
					break;

				case 'del_backup_codes':
					$aAjaxResponse = $this->ajaxExec_DeleteBackupCodes();
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
		$sPass = $oPro->getProcessorLoginIntent()
					  ->getProcessorBackupCodes()
					  ->resetSecret( $this->loadWpUsers()->getCurrentWpUser() );

		foreach ( array( 20, 15, 10, 5 ) as $nPos ) {
			$sPass = substr_replace( $sPass, '-', $nPos, 0 );
		}

		return array(
			'code'    => $sPass,
			'success' => true
		);
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
		$aIds = $this->getOpt( 'antibot_form_ids', array() );
		return is_array( $aIds ) ? $aIds : array();
	}

	public function onWpEnqueueJs() {
		parent::onWpEnqueueJs();

		if ( $this->isEnabledBotJs() ) {
			$oConn = $this->getCon();

			$sAsset = 'shield-antibot';
			$sUnique = $this->prefix( $sAsset );
			wp_register_script(
				$sUnique,
				$oConn->getPluginUrl_Js( $sAsset.'.js' ),
				array( 'jquery' ),
				$oConn->getVersion(),
				true
			);
			wp_enqueue_script( $sUnique );

			wp_localize_script(
				$sUnique,
				'icwp_wpsf_vars_lpantibot',
				array(
					'form_selectors' => implode( ',', $this->getAntiBotFormSelectors() ),
					'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
					'cbname'         => $this->getGaspKey(),
					'strings'        => array(
						'label' => $this->getTextImAHuman(),
						'alert' => $this->getTextPleaseCheckBox(),
					),
					'flags'          => array(
						'gasp'  => $this->isEnabledGaspCheck(),
						'recap' => $this->isGoogleRecaptchaEnabled(),
					)
				)
			);

			if ( $this->isGoogleRecaptchaEnabled() ) {
				/** @var ICWP_WPSF_Processor_LoginProtect $oPro */
				$oPro = $this->getProcessor();
				$oPro->setRecaptchaToEnqueue();
			}
		}
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_DeleteBackupCodes() {

		/** @var ICWP_WPSF_Processor_LoginProtect $oPro */
		$oPro = $this->loadProcessor();
		$oPro->getProcessorLoginIntent()
			 ->getProcessorBackupCodes()
			 ->deleteSecret( $this->loadWpUsers()->getCurrentWpUser() );
		$this->setFlashAdminNotice( _wpsf__( 'Multi-factor login backup code has been removed from your profile' ) );
		return array(
			'success' => true
		);
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		wp_localize_script(
			$this->prefix( 'global-plugin' ),
			'icwp_wpsf_vars_lg',
			array(
				'ajax_gen_backup_codes' => $this->getAjaxActionData( 'gen_backup_codes' ),
				'ajax_del_backup_codes' => $this->getAjaxActionData( 'del_backup_codes' ),
			)
		);
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'Login Guard' ),
				'sub'   => _wpsf__( 'Brute Force Protection & Identity Verification' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasBotCheck = $this->isEnabledGaspCheck() || $this->isGoogleRecaptchaEnabled();

			$bBotLogin = $bHasBotCheck && $this->isProtectLogin();
			$bBotRegister = $bHasBotCheck && $this->isProtectRegister();
			$bBotPassword = $bHasBotCheck && $this->isProtectLostPassword();
			$aThis[ 'key_opts' ][ 'bot_login' ] = array(
				'name'    => _wpsf__( 'Brute Force Login' ),
				'enabled' => $bBotLogin,
				'summary' => $bBotLogin ?
					_wpsf__( 'Login forms are protected against bot attacks' )
					: _wpsf__( 'Login forms are not protected against brute force bot attacks' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			);
			$aThis[ 'key_opts' ][ 'bot_register' ] = array(
				'name'    => _wpsf__( 'Bot User Register' ),
				'enabled' => $bBotRegister,
				'summary' => $bBotRegister ?
					_wpsf__( 'Registration forms are protected against bot attacks' )
					: _wpsf__( 'Registration forms are not protected against automated bots' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			);
			$aThis[ 'key_opts' ][ 'bot_password' ] = array(
				'name'    => _wpsf__( 'Brute Force Lost Password' ),
				'enabled' => $bBotPassword,
				'summary' => $bBotPassword ?
					_wpsf__( 'Lost Password forms are protected against bot attacks' )
					: _wpsf__( 'Lost Password forms are not protected against automated bots' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			);

			$bHas2Fa = $this->isEmailAuthenticationActive()
					   || $this->isEnabledGoogleAuthenticator() || $this->isYubikeyActive();
			$aThis[ 'key_opts' ][ '2fa' ] = array(
				'name'    => _wpsf__( 'Identity Verification' ),
				'enabled' => $bHas2Fa,
				'summary' => $bHas2Fa ?
					_wpsf__( 'At least 1 2FA option is enabled' )
					: _wpsf__( 'No 2FA options, such as Google Authenticator, are active' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_2fa_email' ),
			);
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
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Login Guard blocks all automated and brute force attempts to log in to your site.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Login Guard' ) ) )
				);
				break;

			case 'section_recaptcha' :
				$sTitle = 'Google reCAPTCHA';
				$sTitleShort = 'reCAPTCHA';
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Adds Google reCAPTCHA to the Login Forms.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Keep this turned on.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Note' ), _wpsf__( "You will need to register for Google reCAPTCHA keys and store them in the Shield 'Dashboard' settings." ) ),
				);
				break;

			case 'section_rename_wplogin' :
				$sTitle = _wpsf__( 'Hide WordPress Login Page' );
				$sTitleShort = sprintf( _wpsf__( 'Rename "%s"' ), 'wp-login.php' );
				$sTitleShort = _wpsf__( 'Hide Login Page' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you.' ) )
				);
				break;

			case 'section_multifactor_authentication' :
				$sTitle = _wpsf__( 'Multi-Factor Authentication' );
				$sTitleShort = _wpsf__( 'Multi-Factor Auth' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.' ) ),
					_wpsf__( 'You may combine multiple authentication factors for increased security.' )
				);
				break;

			case 'section_2fa_email' :
				$sTitle = _wpsf__( 'Email Two-Factor Authentication' );
				$sTitleShort = _wpsf__( '2FA - Email' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Verifies the identity of users who log in to your site using email-based one-time-passwords.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ).' '._wpsf__( 'However, if your host blocks email sending you may lock yourself out.' ) ),
					sprintf( '%s: %s', _wpsf__( 'Note' ), _wpsf__( 'You may combine multiple authentication factors for increased security.' ) )
				);
				break;

			case 'section_2fa_ga' :
				$sTitle = _wpsf__( 'Google Authenticator Two-Factor Authentication' );
				$sTitleShort = _wpsf__( '2FA - Google Authenticator' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Verifies the identity of users who log in to your site using Google Authenticator one-time-passwords.' ) ),
					sprintf( '%s: %s', _wpsf__( 'Note' ), _wpsf__( 'You may combine multiple authentication factors for increased security.' ) )
				);
				break;

			case 'section_brute_force_login_protection' :
				$sTitle = _wpsf__( 'Brute Force Login Protection' );
				$sTitleShort = _wpsf__( 'reCAPTCHA & Bots' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Blocks brute force hacking attacks against your login and registration pages.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
				);
				break;

			case 'section_yubikey_authentication' :
				$sTitle = _wpsf__( 'Yubikey Two-Factor Authentication' );
				$sTitleShort = _wpsf__( '2FA -Yubikey' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Verifies the identity of users who log in to your site using Yubikey one-time-passwords.' ) ),
					sprintf( '%s: %s', _wpsf__( 'Note' ), _wpsf__( 'You may combine multiple authentication factors for increased security.' ) )
				);
				break;

			default:
				list( $sTitle, $sTitleShort, $aSummary ) = $this->loadStrings_SectionTitlesDefaults( $aOptionsParams );
		}

		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
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
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'rename_wplogin_path' :
				$sName = _wpsf__( 'Hide WP Login Page' );
				$sSummary = _wpsf__( 'Hide The WordPress Login Page' );
				$sDescription = _wpsf__( 'Creating a path here will disable your wp-login.php' )
								.'<br />'
								.sprintf( _wpsf__( 'Only letters and numbers are permitted: %s' ), '<strong>abc123</strong>' )
								.'<br />'
								.sprintf( _wpsf__( 'Your current login URL is: %s' ), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' );
				break;

			case 'enable_chained_authentication' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Multi-Factor Authentication' ) );
				$sSummary = _wpsf__( 'Require All Active Authentication Factors' );
				$sDescription = _wpsf__( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.' );
				break;

			case 'mfa_skip' :
				$sName = _wpsf__( 'Multi-Factor By-Pass' );
				$sSummary = _wpsf__( 'A User Can By-Pass Multi-Factor Authentication (MFA) For The Set Number Of Days' );
				$sDescription = _wpsf__( 'Enter the number of days a user can by-pass future MFA after a successful MFA-login. 0 to disable.' );
				break;

			case 'allow_backupcodes' :
				$sName = _wpsf__( 'Allow Backup Codes' );
				$sSummary = _wpsf__( 'Allow Users To Generate A Backup Code' );
				$sDescription = _wpsf__( 'Allow users to generate a backup code that can be used to login if MFA factors are unavailable.' );
				break;

			case 'enable_google_authenticator' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Google Authenticator' ) );
				$sSummary = _wpsf__( 'Allow Users To Use Google Authenticator' );
				$sDescription = _wpsf__( 'When enabled, users will have the option to add Google Authenticator to their WordPress user profile' );
				break;

			case 'enable_email_authentication' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Email Authentication' ) );
				$sSummary = sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__( 'Email' ) );
				$sDescription = _wpsf__( 'All users will be required to verify their login by email-based two-factor authentication.' );
				break;

			case 'two_factor_auth_user_roles' :
				$sName = sprintf( '%s - %s', _wpsf__( 'Enforce' ), _wpsf__( 'Email Authentication' ) );
				$sSummary = _wpsf__( 'All User Roles Subject To Email Authentication' );
				$sDescription = _wpsf__( 'Enforces email-based authentication on all users with the selected roles.' )
								.'<br /><strong>'.sprintf( '%s: %s', _wpsf__( 'Note' ), sprintf( _wpsf__( 'This setting only applies to %s.' ), _wpsf__( 'Email Authentication' ) ) ).'</strong>';
				break;

			case 'enable_google_recaptcha_login' :
				$sName = _wpsf__( 'Google reCAPTCHA' );
				$sSummary = _wpsf__( 'Protect WordPress Account Access Requests With Google reCAPTCHA' );
				$sDescription = _wpsf__( 'Use Google reCAPTCHA on the user account forms such as login, register, etc.' ).'<br />'
								.sprintf( _wpsf__( 'Use of any theme other than "%s", requires a Pro license.' ), _wpsf__( 'Light Theme' ) )
								.'<br/>'.sprintf( '%s - %s', _wpsf__( 'Note' ), _wpsf__( "You'll need to setup your Google reCAPTCHA API Keys in 'General' settings." ) )
								.'<br/><strong>'.sprintf( '%s - %s', _wpsf__( 'Important' ), _wpsf__( "Some forms are more dynamic than others so if you experience problems, please use non-Invisible reCAPTCHA." ) ).'</strong>';
				break;

			case 'google_recaptcha_style_login' : // Unused
				$sName = _wpsf__( 'reCAPTCHA Style' );
				$sSummary = _wpsf__( 'How Google reCAPTCHA Will Be Displayed' );
				$sDescription = _wpsf__( 'You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha' );
				break;

			case 'bot_protection_locations' :
				$sName = _wpsf__( 'Protection Locations' );
				$sSummary = _wpsf__( 'Which Forms Should Be Protected' );
				$sDescription = _wpsf__( 'Choose the forms for which bot protection measures will be deployed.' ).'<br />'
								.sprintf( '%s - %s', _wpsf__( 'Note' ), sprintf( _wpsf__( "Use with 3rd party systems such as %s, requires a Pro license." ), 'WooCommerce' ) );
				break;

			case 'enable_login_gasp_check' :
				$sName = _wpsf__( 'Bot Protection' );
				$sSummary = _wpsf__( 'Protect WP Login From Automated Login Attempts By Bots' );
				$sDescription = _wpsf__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.' )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Recommendation' ), _wpsf__( 'ON' ) );
				break;

			case 'enable_antibot_js' :
				$sName = _wpsf__( 'AntiBot JS' );
				$sSummary = _wpsf__( 'Use AntiBot JS Includes For Custom 3rd Party Form' );
				$sDescription = _wpsf__( 'Important: This is experimental. Please contact support for further assistance.' );
				break;

			case 'antibot_form_ids' :
				$sName = _wpsf__( 'AntiBot Forms' );
				$sSummary = _wpsf__( 'Enter The Selectors Of The 3rd Party Login Forms For Use With AntiBot JS' );
				$sDescription = _wpsf__( 'For use with the AntiBot JS option.' )
								.' '._wpsf__( 'IDs are prefixed with "#".' )
								.' '._wpsf__( 'Classes are prefixed with ".".' )
								.'<br />'._wpsf__( 'IDs are preferred over classes.' );
				break;

			case 'login_limit_interval' :
				$sName = _wpsf__( 'Cooldown Period' );
				$sSummary = _wpsf__( 'Limit account access requests to every X seconds' );
				$sDescription = _wpsf__( 'WordPress will process only ONE account access attempt per number of seconds specified.' )
								.'<br />'._wpsf__( 'Zero (0) turns this off.' )
								.' '.sprintf( '%s: %s', _wpsf__( 'Default' ), $this->getOptionsVo()
																				   ->getOptDefault( 'login_limit_interval' ) );
				break;

			case 'enable_user_register_checking' :
				$sName = _wpsf__( 'User Registration' );
				$sSummary = _wpsf__( 'Apply Brute Force Protection To User Registration And Lost Passwords' );
				$sDescription = _wpsf__( 'When enabled, settings in this section will also apply to new user registration and users trying to reset passwords.' );
				break;

			case 'enable_yubikey' :
				$sName = _wpsf__( 'Enable Yubikey Authentication' );
				$sSummary = _wpsf__( 'Turn On / Off Yubikey Authentication On This Site' );
				$sDescription = _wpsf__( 'Combined with your Yubikey API details this will form the basis of your Yubikey Authentication' );
				break;

			case 'yubikey_app_id' :
				$sName = _wpsf__( 'Yubikey App ID' );
				$sSummary = _wpsf__( 'Your Unique Yubikey App ID' );
				$sDescription = _wpsf__( 'Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication' )
								._wpsf__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.' );
				break;

			case 'yubikey_api_key' :
				$sName = _wpsf__( 'Yubikey API Key' );
				$sSummary = _wpsf__( 'Your Unique Yubikey App API Key' );
				$sDescription = _wpsf__( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.' )
								._wpsf__( 'Please review the info link on how to get your own Yubikey App ID and API Key.' );
				break;

			case 'yubikey_unique_keys' :
				$sName = _wpsf__( 'Yubikey Unique Keys' );
				$sSummary = _wpsf__( 'This method for Yubikeys is no longer supported. Please see your user profile' );
				$sDescription = '<strong>'.sprintf( '%s: %s', _wpsf__( 'Format' ), 'Username,Yubikey' ).'</strong>'
								.'<br />- '._wpsf__( 'Provide Username<->Yubikey Pairs that are usable for this site.' )
								.'<br />- '._wpsf__( 'If a Username if not assigned a Yubikey, Yubikey Authentication is OFF for that user.' )
								.'<br />- '._wpsf__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.' );
				break;

			case 'text_imahuman' :
				$sName = _wpsf__( 'GASP Checkbox Text' );
				$sSummary = _wpsf__( 'The User Message Displayed Next To The GASP Checkbox' );
				$sDescription = _wpsf__( "You can change the text displayed to the user beside the checkbox if you need a custom message." )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Default' ), $this->getTextOptDefault( 'text_imahuman' ) );
				break;

			case 'text_pleasecheckbox' :
				$sName = _wpsf__( 'GASP Alert Text' );
				$sSummary = _wpsf__( "The Message Displayed If The User Doesn't Check The Box" );
				$sDescription = _wpsf__( "You can change the text displayed to the user in the alert message if they don't check the box." )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Default' ), $this->getTextOptDefault( 'text_pleasecheckbox' ) );
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
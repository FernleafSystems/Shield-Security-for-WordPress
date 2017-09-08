<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_LoginProtect', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		/**
		 * A action added to WordPress 'init' hook
		 */
		public function onWpInit() {
			parent::onWpInit();

			$oDp = $this->loadDataProcessor();
			// User has clicked a link in their email to verify they can send email.
			if ( $oDp->FetchGet( 'wpsf-action' ) == 'emailsendverify' ) {
				if ( $this->getTwoAuthSecretKey() == $oDp->FetchGet( 'authkey' ) ) {
					$this->setIfCanSendEmail( true )
						 ->setBypassAdminProtection( true )
						 ->savePluginOptions();
					$this->loadWpFunctions()->redirectToLogin();
				}
			}
		}

		/**
		 * @return bool
		 */
		public function getIfUseLoginIntentPage() {
			return $this->getOptIs( 'use_login_intent_page', true );
		}

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->prefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		protected function doExtraSubmitProcessing() {
			/**
			$oWp = $this->loadWpFunctionsProcessor();
			$sCustomLoginPath = $this->cleanLoginUrlPath();
			if ( !empty( $sCustomLoginPath ) && $oWp->getIsPermalinksEnabled() ) {
				$oWp->resavePermalinks();
			}
		 */
			if ( $this->getIsEmailAuthenticationOptionOn() && !$this->getIfCanSendEmailVerified() ) {
				$this
					->setIfCanSendEmail( false )
					->sendEmailVerifyCanSend();
			}
		}

		public function doPrePluginOptionsSave() {

			$this->cleanLoginUrlPath();

			if ( $this->getOpt( 'login_limit_interval' ) < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'login_limit_interval' );
			}

			$aTwoFactorAuthRoles = $this->getOpt( 'two_factor_auth_user_roles' );
			if ( empty($aTwoFactorAuthRoles) || !is_array( $aTwoFactorAuthRoles ) ) {
				$this->setOpt( 'two_factor_auth_user_roles', $this->getTwoFactorUserAuthRoles( true ) );
			}
		}

		/**
		 * @return string
		 */
		protected function generateCanSendEmailVerifyLink() {
			$aQueryArgs = array(
				'authkey' 		=> $this->getTwoAuthSecretKey(),
				'wpsf-action'	=> 'emailsendverify'
			);
			return add_query_arg( $aQueryArgs, $this->loadWpFunctions()->getHomeUrl() );
		}

		/**
		 * @return boolean
		 */
		public function sendEmailVerifyCanSend() {

			$aMessage = array(
				_wpsf__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.' ),
				_wpsf__( 'This verifies your website can send email and that your account can receive emails sent from your site.' ),
				sprintf( _wpsf__('Verify Link: %s'), $this->generateCanSendEmailVerifyLink() ),
			);
			$sEmailSubject = sprintf( _wpsf__( 'Email Sending Verification For %s' ), $this->loadWpFunctions()->getHomeUrl() );

			$bResult = $this->getEmailProcessor()->sendEmailTo( get_bloginfo( 'admin_email' ), $sEmailSubject, $aMessage );
			return $bResult;
		}

		/**
		 * @return string
		 */
		private function cleanLoginUrlPath() {
			$sCustomLoginPath = $this->getCustomLoginPath();
			if ( !empty( $sCustomLoginPath ) ) {
				$sCustomLoginPath = preg_replace( '#[^0-9a-zA-Z-]#', '', trim( $sCustomLoginPath, '/' ) );
				$this->setOpt( 'rename_wplogin_path', $sCustomLoginPath );
			}
			return $sCustomLoginPath;
		}

		/**
		 * @return bool
		 */
		public function getIsCheckingUserRegistrations() {
			return $this->getOptIs( 'enable_user_register_checking', 'Y' );
		}

		/**
		 * @param boolean $fAsDefaults
		 * @return array
		 */
		protected function getTwoFactorUserAuthRoles( $fAsDefaults = false ) {
			$aTwoAuthRoles = array( 'type' => 'multiple_select',
									0	=> _wpsf__('Subscribers'),
									1	=> _wpsf__('Contributors'),
									2	=> _wpsf__('Authors'),
									3	=> _wpsf__('Editors'),
									8	=> _wpsf__('Administrators')
			);
			if ( $fAsDefaults ) {
				unset($aTwoAuthRoles['type']);
				unset($aTwoAuthRoles[0]);
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
		public function getIsCustomLoginPathEnabled() {
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
			return $sKey;
		}

		/**
		 * @return string
		 */
		public function getTwoFactorAuthTableName() {
			return $this->prefix( $this->getDefinition( 'two_factor_auth_table_name' ), '_' );
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
		public function getIsEmailAuthenticationOptionOn() {
			return $this->getOptIs( 'enable_email_authentication', 'Y' );
		}

		/**
		 * Also considers whether email sending ability has been verified
		 * @return bool
		 */
		public function getIsEmailAuthenticationEnabled() {
			return $this->getIfCanSendEmail() && $this->getIsEmailAuthenticationOptionOn();
		}

		/**
		 * @return bool
		 */
		public function getIsGoogleRecaptchaEnabled() {
			return ( $this->getOptIs( 'enable_google_recaptcha', 'Y' ) && $this->getIsGoogleRecaptchaReady() );
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
		public function getIfCanSendEmail() {
			return $this->getCanSendEmailVerifiedAt() != 0;
		}

		/**
		 * @return bool
		 */
		public function getIfCanSendEmailVerified() {
			return $this->getCanSendEmailVerifiedAt() > 0;
		}

		/**
		 * @return bool
		 */
		public function isChainedAuth() {
			return $this->getOptIs( 'enable_chained_authentication', 'Y' );
		}

		/**
		 * @param bool $bCan
		 * @return $this
		 */
		public function setIfCanSendEmail( $bCan ) {
			$nCurrentDateAt = $this->getCanSendEmailVerifiedAt();
			if ( $bCan ) {
				$nDateAt = ( $nCurrentDateAt <= 0 ) ? $this->loadDataProcessor()->time() : $nCurrentDateAt;
			}
			else {
				$nDateAt = 0;
			}
			$this->setOpt( 'email_can_send_verified_at', $nDateAt );
			return $this;
		}

		/**
		 * @return string
		 */
		public function getLoginIntentRequestFlag() {
			return $this->prefix( 'login-intent-request' );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['slug'];
			switch( $sSectionSlug ) {

				case 'section_enable_plugin_feature_login_protection' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Login Protection blocks all automated and brute force attempts to log in to your site.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Login Protection' ) ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_bypass_login_protection' :
					$sTitle = _wpsf__( 'By-Pass Login Protection' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Compatibility with XML-RPC services such as the WordPress iPhone and Android Apps.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Keep this turned off unless you know you need it.' ) )
					);
					$sTitleShort = _wpsf__( 'By-Pass' );
					break;

				case 'section_rename_wplogin' :
					$sTitle = _wpsf__( 'Rename WP Login Page' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you.' ) )
					);
					$sTitleShort = sprintf( _wpsf__( 'Rename "%s"' ), 'wp-login.php' );
					break;

				case 'section_multifactor_authentication' :
					$sTitle = _wpsf__( 'Multi-Factor Authentication' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ). ' '._wpsf__( 'However, if your host blocks email sending you may lock yourself out.' ) ),
						sprintf( _wpsf__( 'Note: %s' ), _wpsf__( 'You may combine multiple authentication factors for increased security.' ) )
					);
					$sTitleShort = _wpsf__( '2-Factor Auth' );
					break;

				case 'section_brute_force_login_protection' :
					$sTitle = _wpsf__( 'Brute Force Login Protection' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Blocks brute force hacking attacks against your login and registration pages.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) )
					);
					$sTitleShort = _wpsf__( 'Brute Force' );
					break;

				case 'section_yubikey_authentication' :
					$sTitle = _wpsf__( 'Yubikey Authentication' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ). ' '._wpsf__( 'Note: you must own the appropriate Yubikey hardware device.' ) )
					);
					$sTitleShort = _wpsf__( 'Yubikey' );
					break;

				case 'section_user_messages' :
					$sTitle = _wpsf__( 'User Messages' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Customize all the messages displayed to the user by the plugin.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use this section if you need to communicate to the user in a particular manner.' ) )
					);
					$sTitleShort = _wpsf__( 'User Messages' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['title'] = $sTitle;
			$aOptionsParams['summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['title_short'] = $sTitleShort;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {
			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_login_protect' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'enable_xmlrpc_compatibility' :
					$sName = _wpsf__( 'XML-RPC Compatibility' );
					$sSummary = _wpsf__( 'Allow Login Through XML-RPC To By-Pass Login Protection Rules' );
					$sDescription = _wpsf__( 'Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App.' );
					break;

				case 'rename_wplogin_path' :
					$sName = _wpsf__( 'Rename WP Login' );
					$sSummary = _wpsf__( 'Rename The WordPress Login Page' );
					$sDescription = _wpsf__( 'Creating a path here will disable your wp-login.php' )
						.'<br />'
						.sprintf( _wpsf__( 'Only letters and numbers are permitted: %s'), '<strong>abc123</strong>' )
						.'<br />'
						.sprintf( _wpsf__( 'Your current login URL is: %s'), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' )
					;
					break;

				case 'enable_chained_authentication' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Multi-Factor Authentication' ) );
					$sSummary = _wpsf__( 'Require All Active Authentication Factors' );
					$sDescription = _wpsf__( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.' );
					break;

				case 'enable_google_authenticator' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Google Authenticator' ) );
					$sSummary = _wpsf__( 'Allow Users To Use Google Authenticator' );
					$sDescription = _wpsf__('When enabled, users will have the option to add Google Authenticator to their WordPress user profile');
					break;

				case 'enable_email_authentication' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Email Authentication' ) );
					$sSummary = sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__('Email') );
					$sDescription = _wpsf__( 'All users will be required to verify their login by email-based two-factor authentication.' );
					break;

				case 'two_factor_auth_user_roles' :
					$sName = sprintf( _wpsf__( 'Enforce - %s' ), _wpsf__( 'Email Authentication' ) );
					$sSummary = _wpsf__( 'All User Roles Subject To Email Authentication' );
					$sDescription = _wpsf__( 'Enforces email-based authentication on all users with the selected roles.' )
						. '<br /><strong>' . sprintf( _wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'This setting only applies to %s.' ), _wpsf__( 'Email Authentication' ) ) ).'</strong>';
					break;

				case 'enable_google_recaptcha' :
					$sName = _wpsf__( 'Google reCAPTCHA' );
					$sSummary = _wpsf__( 'Enable Google reCAPTCHA' );
					$sDescription = _wpsf__( 'Use Google reCAPTCHA on the login screen.' );
					break;

				case 'enable_login_gasp_check' :
					$sName = _wpsf__( 'G.A.S.P Protection' );
					$sSummary = _wpsf__( 'Use G.A.S.P. Protection To Prevent Login Attempts By Bots' );
					$sDescription = _wpsf__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.' )
						. ' ' . sprintf( _wpsf__( 'Recommended: %s' ), _wpsf__( 'ON' ) );
					break;

				case 'login_limit_interval' :
					$sName = _wpsf__( 'Login Cooldown Interval' );
					$sSummary = _wpsf__( 'Limit login attempts to every X seconds' );
					$sDescription = _wpsf__( 'WordPress will process only ONE login attempt for every number of seconds specified.' )
						. '<br />' . _wpsf__( 'Zero (0) turns this off.' )
						. ' ' . sprintf( _wpsf__( 'Default: "%s".' ), $this->getOptionsVo()->getOptDefault( 'login_limit_interval' ) );
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
						. _wpsf__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.' );
					break;

				case 'yubikey_api_key' :
					$sName = _wpsf__( 'Yubikey API Key' );
					$sSummary = _wpsf__( 'Your Unique Yubikey App API Key' );
					$sDescription = _wpsf__( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.' )
						. _wpsf__( 'Please review the info link on how to get your own Yubikey App ID and API Key.' );
					break;

				case 'yubikey_unique_keys' :
					$sName = _wpsf__( 'Yubikey Unique Keys' );
					$sSummary = _wpsf__( 'This method for Yubikeys is no longer supported. Please see your user profile' );
					$sDescription = '<strong>' . sprintf( _wpsf__( 'Format: %s' ), 'Username,Yubikey' ) . '</strong>'
						. '<br />- ' . _wpsf__( 'Provide Username<->Yubikey Pairs that are usable for this site.' )
						. '<br />- ' . _wpsf__( 'If a Username if not assigned a Yubikey, Yubikey Authentication is OFF for that user.' )
						. '<br />- ' . _wpsf__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.' );
					break;

				case '3pty_support_woocommerce' :
					$sName = _wpsf__( 'Woocommerce Support' );
					$sSummary = _wpsf__( 'Add Support For Woocommerce Login and Password Reset Pages' );
					$sDescription = _wpsf__( 'Woocommerce is a 3rd party plugin that uses its own custom login and password reset forms.' );
					break;

				case 'text_pleasecheckbox' :
					$sName = _wpsf__( 'GASP Checkbox Text' );
					$sSummary = _wpsf__( 'The User Message Displayed Next To The GASP Checkbox' );
					$sDescription = _wpsf__( "You can change the text displayed to the user beside the checkbox if you need a custom message." )
									. '<br />' . sprintf( _wpsf__( 'Default: "%s".' ), $this->getOptionsVo()->getOptDefault( 'text_imahuman' ) );
					break;

				case 'text_imahuman' :
					$sName = _wpsf__( 'GASP Alert Text' );
					$sSummary = _wpsf__( "The Message Displayed If The User Doesn't Check The Box" );
					$sDescription = _wpsf__( "You can change the text displayed to the user in the alert message if they don't check the box." )
									. '<br />' . sprintf( _wpsf__( 'Default: "%s".' ), $this->getOptionsVo()->getOptDefault( 'text_imahuman' ) );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;
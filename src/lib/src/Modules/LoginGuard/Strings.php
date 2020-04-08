<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'botbox_fail'             => [
				__( 'User "%s" attempted "%s" but Bot checkbox was not found.', 'wp-simple-firewall' )
			],
			'cooldown_fail'           => [
				__( 'Login/Register request triggered cooldown and was blocked.', 'wp-simple-firewall' )
			],
			'honeypot_fail'           => [
				__( 'User "%s" attempted %s but they were caught by the honeypot.', 'wp-simple-firewall' )
			],
			'2fa_backupcode_verified' => [
				__( 'User "%s" verified their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_backupcode_fail'     => [
				__( 'User "%s" failed to verify their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_email_verified'      => [
				__( 'User "%s" verified their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_email_verify_fail'   => [
				__( 'User "%s" failed to verify their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_googleauth_verified' => [
				__( 'User "%s" verified their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_googleauth_fail'     => [
				__( 'User "%s" failed to verify their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_yubikey_verified'    => [
				__( 'User "%s" verified their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_yubikey_fail'        => [
				__( 'User "%s" failed to verify their identity using %s.', 'wp-simple-firewall' )
			],
			'2fa_email_send_success'  => [
				__( 'User "%s" sent two-factor authentication email to verify identity.', 'wp-simple-firewall' )
			],
			'2fa_email_send_fail'     => [
				__( 'Failed to send user "%s" two-factor authentication email.', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_login_protection' :
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Login Guard blocks all automated and brute force attempts to log in to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_recaptcha' :
				$sTitle = 'CAPTCHA';
				$sTitleShort = 'CAPTCHA';
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Adds CAPTCHA to the Login Forms.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this turned on.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( "You will need to register for CAPTCHA keys and store them in the Shield 'Dashboard' settings.", 'wp-simple-firewall' ) ),
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
				return parent::getSectionStrings( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $sOptKey ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$sModName = $oMod->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_login_protect' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
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

			case 'email_any_user_set' :
				$sName = __( 'Allow Any User', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Any User To Turn-On Two-Factor Authentication By Email.', 'wp-simple-firewall' );
				$sDescription = __( 'Any user can turn on two-factor authentication by email from their profile.', 'wp-simple-firewall' );
				break;

			case 'two_factor_auth_user_roles' :
				$sName = sprintf( '%s - %s', __( 'Enforce', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$sSummary = __( 'All User Roles Subject To Email Authentication', 'wp-simple-firewall' );
				$sDescription = __( 'Enforces email-based authentication on all users with the selected roles.', 'wp-simple-firewall' )
								.'<br /><strong>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'This setting only applies to %s.', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) ) ).'</strong>';
				break;

			case 'enable_google_recaptcha_login' :
				$sName = __( 'CAPTCHA', 'wp-simple-firewall' );
				$sSummary = __( 'Protect WordPress Account Access Requests With CAPTCHA', 'wp-simple-firewall' );
				$sDescription = __( 'Use CAPTCHA on the user account forms such as login, register, etc.', 'wp-simple-firewall' ).'<br />'
								.sprintf( __( 'Use of any theme other than "%s", requires a Pro license.', 'wp-simple-firewall' ), __( 'Light Theme', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( "You'll need to setup your CAPTCHA API Keys in 'General' settings.", 'wp-simple-firewall' ) )
								.'<br/><strong>'.sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( "Some forms are more dynamic than others so if you experience problems, please use non-Invisible CAPTCHA.", 'wp-simple-firewall' ) ).'</strong>';
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

			case 'antibot_form_ids' :
				$sName = __( 'AntiBot Forms', 'wp-simple-firewall' );
				$sSummary = __( 'Enter The Selectors Of The 3rd Party Login Forms For Use With AntiBot JS', 'wp-simple-firewall' );
				$sDescription = __( 'Provide DOM selectors to attached AntiBot protection to any form.', 'wp-simple-firewall' )
								.'<br />'.__( 'IDs are prefixed with "#".', 'wp-simple-firewall' )
								.'<br />'.__( 'Classes are prefixed with ".".', 'wp-simple-firewall' )
								.'<br />'.__( 'IDs are preferred over classes.', 'wp-simple-firewall' );
				break;

			case 'login_limit_interval' :
				$sName = __( 'Cooldown Period', 'wp-simple-firewall' );
				$sSummary = __( 'Limit account access requests to every X seconds', 'wp-simple-firewall' );
				$sDescription = __( 'WordPress will process only ONE account access attempt per number of seconds specified.', 'wp-simple-firewall' )
								.'<br />'.__( 'Zero (0) turns this off.', 'wp-simple-firewall' )
								.' '.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $oMod->getOptions()
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
								.'<br />'.__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.', 'wp-simple-firewall' );
				break;

			case 'yubikey_api_key' :
				$sName = __( 'Yubikey API Key', 'wp-simple-firewall' );
				$sSummary = __( 'Your Unique Yubikey App API Key', 'wp-simple-firewall' );
				$sDescription = __( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.', 'wp-simple-firewall' )
								.'<br />'.__( 'Please review the info link on how to get your own Yubikey App ID and API Key.', 'wp-simple-firewall' );
				break;

			case 'yubikey_unique_keys' :
				$sName = __( 'Yubikey Unique Keys', 'wp-simple-firewall' );
				$sSummary = __( 'This method for Yubikeys is no longer supported. Please see your user profile', 'wp-simple-firewall' );
				$sDescription = '<strong>'.sprintf( '%s: %s', __( 'Format', 'wp-simple-firewall' ), 'Username,Yubikey' ).'</strong>'
								.'<br />- '.__( 'Provide Username<->Yubikey Pairs that are usable for this site.', 'wp-simple-firewall' )
								.'<br />- '.__( 'If a Username is not assigned a Yubikey, Yubikey Authentication is OFF for that user.', 'wp-simple-firewall' )
								.'<br />- '.__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.', 'wp-simple-firewall' );
				break;

			case 'text_imahuman' :
				$sName = __( 'GASP Checkbox Text', 'wp-simple-firewall' );
				$sSummary = __( 'The User Message Displayed Next To The GASP Checkbox', 'wp-simple-firewall' );
				$sDescription = __( "You can change the text displayed to the user beside the checkbox if you need a custom message.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $oMod->getTextOptDefault( 'text_imahuman' ) );
				break;

			case 'text_pleasecheckbox' :
				$sName = __( 'GASP Alert Text', 'wp-simple-firewall' );
				$sSummary = __( "The Message Displayed If The User Doesn't Check The Box", 'wp-simple-firewall' );
				$sDescription = __( "You can change the text displayed to the user in the alert message if they don't check the box.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $oMod->getTextOptDefault( 'text_pleasecheckbox' ) );
				break;

			// removed 9.0
			case 'enable_antibot_js' :
				$sName = __( 'AntiBot JS', 'wp-simple-firewall' );
				$sSummary = __( 'Use AntiBot JS Includes For Custom 3rd Party Forms', 'wp-simple-firewall' );
				$sDescription = __( 'Important: This is experimental. Please contact support for further assistance.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}
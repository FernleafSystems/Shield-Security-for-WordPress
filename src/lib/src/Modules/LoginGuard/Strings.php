<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
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
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_login_protection' :
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Login Guard blocks all automated and brute force attempts to log in to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) ) )
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

			case 'section_hardware_authentication' :
				$sTitle = __( 'Hardware 2-Factor Authentication', 'wp-simple-firewall' );
				$sTitleShort = __( 'Hardware 2FA', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Yubikey one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$modName = $mod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_login_protect' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName );
				break;

			case 'rename_wplogin_path' :
				$name = __( 'Hide WP Login Page', 'wp-simple-firewall' );
				$summary = __( 'Hide The WordPress Login Page', 'wp-simple-firewall' );
				$desc = __( 'Creating a path here will disable your wp-login.php', 'wp-simple-firewall' )
						.'<br />'
						.sprintf( __( 'Only letters and numbers are permitted: %s', 'wp-simple-firewall' ), '<strong>abc123</strong>' )
						.'<br />'
						.sprintf( __( 'Your current login URL is: %s', 'wp-simple-firewall' ), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' );
				break;

			case 'enable_chained_authentication' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Multi-Factor Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'Require All Active Authentication Factors', 'wp-simple-firewall' );
				$desc = __( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.', 'wp-simple-firewall' );
				break;

			case 'mfa_skip' :
				$name = __( 'Multi-Factor Bypass', 'wp-simple-firewall' );
				$summary = __( 'A User Can Bypass Multi-Factor Authentication (MFA) For The Set Number Of Days', 'wp-simple-firewall' );
				$desc = __( 'Enter the number of days a user can bypass future MFA after a successful MFA-login. 0 to disable.', 'wp-simple-firewall' );
				break;

			case 'allow_backupcodes' :
				$name = __( 'Allow Backup Codes', 'wp-simple-firewall' );
				$summary = __( 'Allow Users To Generate A Backup Code', 'wp-simple-firewall' );
				$desc = __( 'Allow users to generate a backup code that can be used to login if MFA factors are unavailable.', 'wp-simple-firewall' );
				break;

			case 'enable_google_authenticator' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) );
				$summary = __( 'Allow Users To Use Google Authenticator', 'wp-simple-firewall' );
				$desc = __( 'When enabled, users will have the option to add Google Authenticator to their WordPress user profile', 'wp-simple-firewall' );
				break;

			case 'enable_email_authentication' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = sprintf( __( 'Two-Factor Login Authentication By %s', 'wp-simple-firewall' ), __( 'Email', 'wp-simple-firewall' ) );
				$desc = __( 'All users will be required to verify their login by email-based two-factor authentication.', 'wp-simple-firewall' );
				break;

			case 'email_any_user_set' :
				$name = __( 'Allow Any User', 'wp-simple-firewall' );
				$summary = __( 'Allow Any User To Turn-On Two-Factor Authentication By Email.', 'wp-simple-firewall' );
				$desc = __( 'Any user can turn on two-factor authentication by email from their profile.', 'wp-simple-firewall' );
				break;

			case 'two_factor_auth_user_roles' :
				$name = sprintf( '%s - %s', __( 'Enforce', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'All User Roles Subject To Email Authentication', 'wp-simple-firewall' );
				$desc = __( 'Enforces email-based authentication on all users with the selected roles.', 'wp-simple-firewall' )
						.'<br /><strong>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'This setting only applies to %s.', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) ) ).'</strong>';
				break;

			case 'enable_google_recaptcha_login' :
				$name = __( 'CAPTCHA', 'wp-simple-firewall' );
				$summary = __( 'Protect WordPress Account Access Requests With CAPTCHA', 'wp-simple-firewall' );
				$desc = __( 'Use CAPTCHA on the user account forms such as login, register, etc.', 'wp-simple-firewall' ).'<br />'
						.sprintf( __( 'Use of any theme other than "%s", requires a Pro license.', 'wp-simple-firewall' ), __( 'Light Theme', 'wp-simple-firewall' ) )
						.'<br/>'.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( "You'll need to setup your CAPTCHA API Keys in 'General' settings.", 'wp-simple-firewall' ) )
						.'<br/><strong>'.sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), __( "Some forms are more dynamic than others so if you experience problems, please use non-Invisible CAPTCHA.", 'wp-simple-firewall' ) ).'</strong>';
				break;

			case 'enable_antibot_check' :
				$name = __( 'AntiBot Detection Engine', 'wp-simple-firewall' );
				$summary = __( "Use AntiBot Detection Engine To Detect Bots", 'wp-simple-firewall' );
				$desc = [
					sprintf( __( "AntiBot Detection Engine is %s's exclusive bot-detection technology that removes the needs for CAPTCHA and other challenges.", 'wp-simple-firewall' ),
						$con->getHumanName() ),
					__( 'This feature is designed to replace the CAPTCHA and Bot Protection options.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Switching on this feature will disable the CAPTCHA and Bot Protection settings.", 'wp-simple-firewall' ) )
				];
				break;

			case 'bot_protection_locations' :
				$name = __( 'Protection Locations', 'wp-simple-firewall' );
				$summary = __( 'Which Forms Should Be Protected', 'wp-simple-firewall' );
				$desc = [
					__( 'Choose the forms for which bot protection measures will be deployed.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( "Use with 3rd party systems such as %s, requires a Pro license.", 'wp-simple-firewall' ), 'WooCommerce' ) ),
					sprintf( '<a href="%s">%s</a>', $con->getModule_Integrations()
														->getUrl_DirectLinkToSection( 'section_user_forms' ),
						sprintf( __( "Choose the 3rd party plugins you want %s to also integrate with.", 'wp-simple-firewall' ), $con->getHumanName() ) )
				];
				break;

			case 'enable_login_gasp_check' :
				$name = __( 'Bot Protection', 'wp-simple-firewall' );
				$summary = __( 'Protect WP Login From Automated Login Attempts By Bots', 'wp-simple-firewall' );
				$desc = __( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.', 'wp-simple-firewall' )
						.'<br />'.sprintf( '%s: %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'ON', 'wp-simple-firewall' ) );
				break;

			case 'antibot_form_ids' :
				$name = __( 'AntiBot Forms', 'wp-simple-firewall' );
				$summary = __( 'Enter The Selectors Of The 3rd Party Login Forms For Use With AntiBot JS', 'wp-simple-firewall' );
				$desc = [
					__( 'Provide DOM selectors to attach AntiBot protection to any form.', 'wp-simple-firewall' ),
					__( 'IDs are prefixed with "#".', 'wp-simple-firewall' ),
					__( 'Classes are prefixed with ".".', 'wp-simple-firewall' ),
					__( 'IDs are preferred over classes.', 'wp-simple-firewall' )
				];
				break;

			case 'login_limit_interval' :
				$name = __( 'Cooldown Period', 'wp-simple-firewall' );
				$summary = __( 'Limit account access requests to every X seconds', 'wp-simple-firewall' );
				$desc = __( 'WordPress will process only ONE account access attempt per number of seconds specified.', 'wp-simple-firewall' )
						.'<br />'.__( 'Zero (0) turns this off.', 'wp-simple-firewall' )
						.' '.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $this->getOptions()
																							->getOptDefault( 'login_limit_interval' ) );
				break;

			case 'enable_user_register_checking' :
				$name = __( 'User Registration', 'wp-simple-firewall' );
				$summary = __( 'Apply Brute Force Protection To User Registration And Lost Passwords', 'wp-simple-firewall' );
				$desc = __( 'When enabled, settings in this section will also apply to new user registration and users trying to reset passwords.', 'wp-simple-firewall' );
				break;

			case 'enable_u2f' :
				$name = __( 'Allow U2F', 'wp-simple-firewall' );
				$summary = __( 'Allow Registration Of U2F Devices', 'wp-simple-firewall' );
				$desc = [
					__( 'Allow users to register U2F devices to complete their login.', 'wp-simple-firewall' ),
					__( "Currently only U2F keys are supported. Built-in fingerprint scanners aren't supported (yet).", 'wp-simple-firewall' ),
					__( "Beta! This may only be used when at least 1 other 2FA option is enabled on a user account.", 'wp-simple-firewall' ),
				];
				break;

			case 'enable_yubikey' :
				$name = __( 'Allow Yubikey OTP', 'wp-simple-firewall' );
				$summary = __( 'Allow Yubikey Registration For One Time Passwords', 'wp-simple-firewall' );
				$desc = __( 'Combined with your Yubikey API details this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' );
				break;

			case 'yubikey_app_id' :
				$name = __( 'Yubikey App ID', 'wp-simple-firewall' );
				$summary = __( 'Your Unique Yubikey App ID', 'wp-simple-firewall' );
				$desc = [
					__( 'Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' ),
					__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.', 'wp-simple-firewall' )
				];
				break;

			case 'yubikey_api_key' :
				$name = __( 'Yubikey API Key', 'wp-simple-firewall' );
				$summary = __( 'Your Unique Yubikey App API Key', 'wp-simple-firewall' );
				$desc = __( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.', 'wp-simple-firewall' )
						.'<br />'.__( 'Please review the info link on how to get your own Yubikey App ID and API Key.', 'wp-simple-firewall' );
				break;

			case 'yubikey_unique_keys' :
				$name = __( 'Yubikey Unique Keys', 'wp-simple-firewall' );
				$summary = __( 'This method for Yubikeys is no longer supported. Please see your user profile', 'wp-simple-firewall' );
				$desc = '<strong>'.sprintf( '%s: %s', __( 'Format', 'wp-simple-firewall' ), 'Username,Yubikey' ).'</strong>'
						.'<br />- '.__( 'Provide Username<->Yubikey Pairs that are usable for this site.', 'wp-simple-firewall' )
						.'<br />- '.__( 'If a Username is not assigned a Yubikey, Yubikey Authentication is OFF for that user.', 'wp-simple-firewall' )
						.'<br />- '.__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.', 'wp-simple-firewall' );
				break;

			case 'text_imahuman' :
				$name = __( 'GASP Checkbox Text', 'wp-simple-firewall' );
				$summary = __( 'The User Message Displayed Next To The GASP Checkbox', 'wp-simple-firewall' );
				$desc = __( "You can change the text displayed to the user beside the checkbox if you need a custom message.", 'wp-simple-firewall' )
						.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $mod->getTextOptDefault( 'text_imahuman' ) );
				break;

			case 'text_pleasecheckbox' :
				$name = __( 'GASP Alert Text', 'wp-simple-firewall' );
				$summary = __( "The Message Displayed If The User Doesn't Check The Box", 'wp-simple-firewall' );
				$desc = __( "You can change the text displayed to the user in the alert message if they don't check the box.", 'wp-simple-firewall' )
						.'<br />'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $mod->getTextOptDefault( 'text_pleasecheckbox' ) );
				break;

			// removed 9.0
			case 'enable_antibot_js' :
				$name = __( 'AntiBot JS', 'wp-simple-firewall' );
				$summary = __( 'Use AntiBot JS Includes For Custom 3rd Party Forms', 'wp-simple-firewall' );
				$desc = __( 'Important: This is experimental. Please contact support for further assistance.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}
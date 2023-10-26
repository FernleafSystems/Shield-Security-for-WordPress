<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'botbox_fail'           => [
				'name'  => __( 'BotBox Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" attempted "{{action}}" but Bot checkbox was not found.', 'wp-simple-firewall' ),
				],
			],
			'cooldown_fail'         => [
				'name'  => __( 'Cooldown Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Login/Register request triggered cooldown and was blocked.', 'wp-simple-firewall' )
				],
			],
			'honeypot_fail'         => [
				'name'  => __( 'Honeypot Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" attempted {{action}} but they were caught by the honeypot.', 'wp-simple-firewall' )
				],
			],
			'2fa_success'           => [
				'name'  => __( '2FA Login Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Successful 2FA Login Verification', 'wp-simple-firewall' ),
				],
			],
			'2fa_verify_success'    => [
				'name'  => __( '2FA Verify Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" verified their identity using "{{method}}".', 'wp-simple-firewall' )
				],
			],
			'2fa_verify_fail'       => [
				'name'  => __( '2FA Verify Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" failed to verify their identity using "{{method}}".', 'wp-simple-firewall' )
				],
			],
			'2fa_nonce_verify_fail' => [
				'name'  => __( '2FA Nonce Verify Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to verify a 2FA login "{{user_login}}" using an invalid nonce.', 'wp-simple-firewall' )
				],
			],
			// todo rename to block_login
			'login_block'           => [
				'name'  => __( 'Login Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User login request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_register'        => [
				'name'  => __( 'Registration Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User registration request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_lostpassword'    => [
				'name'  => __( 'Lost Password Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User lost password request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_checkout'        => [
				'name'  => __( 'Checkout Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User attempting checkout request blocked.', 'wp-simple-firewall' ),
				],
			],
			'hide_login_url'        => [
				'name'  => __( 'Hidden Login URL Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Redirecting wp-login due to hidden login URL', 'wp-simple-firewall' ),
				],
			],
		];
	}

	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_login_protection' :
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->mod()
																						->getMainFeatureName() );
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Login Guard blocks all automated and brute force attempts to log in to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_rename_wplogin' :
				$title = __( 'Hide WordPress Login Page', 'wp-simple-firewall' );
				$titleShort = __( 'Hide Login', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'To hide your wp-login.php page from brute force attacks and hacking attempts - if your login page cannot be found, no-one can login.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This is not required for complete security and if your site has irregular or inconsistent configuration it may not work for you.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s. %s',
						__( 'IMPORTANT', 'wp-simple-firewall' ),
						__( 'If you provide access to the WP Login URL in any areas of your site, you will expose your hidden login URL.', 'wp-simple-firewall' ),
						__( 'For example, if you set WordPress to require visitors to login to post comments, this will expose your hidden URL.', 'wp-simple-firewall' )
					),
				];
				break;

			case 'section_twofactor_auth' :
				$title = __( 'Two-Factor Authentication', 'wp-simple-firewall' );
				$titleShort = __( 'Two-Factor Auth', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					__( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' )
				];
				break;

			case 'section_2fa_email' :
				$title = __( 'Email Two-Factor Authentication', 'wp-simple-firewall' );
				$titleShort = __( '2FA By Email', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using email-based one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_2fa_ga' :
				$title = __( 'One-Time Passwords', 'wp-simple-firewall' );
				$titleShort = __( 'One-Time Passwords', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Google Authenticator one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_brute_force_login_protection' :
				$title = __( 'Brute Force Login Protection', 'wp-simple-firewall' );
				$titleShort = __( 'Bots', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks brute force hacking attacks against your login and registration pages.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_hardware_authentication' :
				$title = __( 'Hardware 2-Factor Authentication', 'wp-simple-firewall' );
				$titleShort = __( 'Hardware 2FA', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Yubikey one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => $summary,
		];
	}

	public function getOptionStrings( string $key ) :array {
		$con = self::con();
		$mod = $this->mod();
		$modName = $mod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_login_protect' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;

			case 'rename_wplogin_path' :
				$name = __( 'Hide WP Login & Admin', 'wp-simple-firewall' );
				$summary = __( 'Hide The WordPress Login And Admin Areas', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( "This will cause %s and %s URLs to return HTTP 404 errors while you're not logged-in.", 'wp-simple-firewall' ),
							'<code>/wp-admin/</code>',
							'<code>/wp-login.php</code>'
						)
					),
					sprintf( __( 'Only letters and numbers are permitted: %s', 'wp-simple-firewall' ), '<strong>abc123</strong>' ),
					sprintf( __( 'Your current login URL is: %s', 'wp-simple-firewall' ), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' )
				];
				break;

			case 'rename_wplogin_redirect' :
				$name = __( 'WP Login & Admin Redirect', 'wp-simple-firewall' );
				$summary = __( 'Automatic Redirect URL For Hidden Pages', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically redirect here for any requests made to hidden pages.', 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						sprintf( __( 'Leave this blank to serve a standard "%s" error page.', 'wp-simple-firewall' ), 'HTTP 404 Not Found' )
					),
					sprintf( '%s: %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'Use relative paths from your homepage URL e.g. %s redirects to your homepage (%s).', 'wp-simple-firewall' ),
							'<code>/</code>',
							sprintf( '<code>%s</code>', Services::WpGeneral()->getHomeUrl() )
						)
					),
				];
				break;

			case 'enable_chained_authentication' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Multi-Factor Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'Require All Active Authentication Factors', 'wp-simple-firewall' );
				$desc = [ __( 'When enabled, all multi-factor authentication methods will be applied to a user login. Disable to require only one to login.', 'wp-simple-firewall' ) ];
				break;

			case 'mfa_verify_page' :
				$name = __( '2FA Verification Page', 'wp-simple-firewall' );
				$summary = __( 'Type Of 2FA Verification Page', 'wp-simple-firewall' );
				$desc = [
					__( 'Choose the type of page provided to users for MFA verification.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ),
						__( 'Choose the Custom Shield page if there are conflicts or issues with the WP Login page for 2FA.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'mfa_user_setup_pages' :
				$name = __( '2FA Config For Users', 'wp-simple-firewall' );
				$summary = __( '2FA Config Pages For User Control', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify pages available to users to configure 2FA on their account.', 'wp-simple-firewall' ),
					__( 'At least 1 option must be provided and defaults to the user profile page within the WP admin area.', 'wp-simple-firewall' )
				];
				break;

			case 'mfa_skip' :
				$name = __( '2FA Remember Me', 'wp-simple-firewall' );
				$summary = __( 'A User Can Bypass 2FA For The Set Number Of Days', 'wp-simple-firewall' );
				$desc = [ __( 'The number of days a user can bypass 2FA after a successful 2FA. 0 to disable.', 'wp-simple-firewall' ) ];
				break;

			case 'allow_backupcodes' :
				$name = __( 'Allow Backup Codes', 'wp-simple-firewall' );
				$summary = __( 'Allow Users To Generate A Backup Code', 'wp-simple-firewall' );
				$desc = [
					__( "Allow users to generate a backup 2FA login code.", 'wp-simple-firewall' ),
					__( "These may be used by the user when they don't have access to their normal 2FA methods.", 'wp-simple-firewall' )
				];
				break;

			case 'enable_google_authenticator' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) );
				$summary = __( 'Allow Users To Use Google Authenticator', 'wp-simple-firewall' );
				$desc = [ __( 'When enabled, users will have the option to add Google Authenticator to their WordPress user profile', 'wp-simple-firewall' ) ];
				break;

			case 'enable_email_authentication' :
				$name = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = sprintf( __( 'Two-Factor Login Authentication By %s', 'wp-simple-firewall' ), __( 'Email', 'wp-simple-firewall' ) );
				$desc = [ __( 'All users will be required to verify their login by email-based two-factor authentication.', 'wp-simple-firewall' ) ];
				break;

			case 'email_any_user_set' :
				$name = __( 'Allow Any User', 'wp-simple-firewall' );
				$summary = __( 'Allow Any User To Turn-On Two-Factor Authentication By Email.', 'wp-simple-firewall' );
				$desc = [ __( 'Any user can turn on two-factor authentication by email from their profile.', 'wp-simple-firewall' ) ];
				break;

			case 'two_factor_auth_user_roles' :
				$name = sprintf( '%s - %s', __( 'Enforce', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) );
				$summary = __( 'All User Roles Subject To Email Authentication', 'wp-simple-firewall' );
				$desc = [
					sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ), sprintf( __( 'This setting only applies to %s.', 'wp-simple-firewall' ), __( 'Email Authentication', 'wp-simple-firewall' ) ) ),
					__( 'Enforces email-based authentication on all users with the selected roles.', 'wp-simple-firewall' ),
					__( 'If a user has multiple roles assigned to it, all roles will be checked against this list.', 'wp-simple-firewall' ),
					sprintf( '%s:<br /><ul><li><code>%s</code></li></ul>', __( 'All User Roles Available On This Site', 'wp-simple-firewall' ),
						\implode( '</code></li><li><code>', Services::WpUsers()->getAvailableUserRoles() ) )
				];
				break;

			case 'enable_antibot_check' :
				$name = __( 'AntiBot Detection Engine (ADE)', 'wp-simple-firewall' );
				$summary = __( 'Use ADE To Detect Bots And Block Brute Force Logins', 'wp-simple-firewall' );
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
					sprintf( '<a href="%s">%s</a>', $con->plugin_urls->modCfgSection( $con->getModule_Integrations(), 'section_user_forms' ),
						sprintf( __( "Choose the 3rd party plugins you want %s to also integrate with.", 'wp-simple-firewall' ), $con->getHumanName() ) )
				];
				break;

			case 'enable_login_gasp_check' :
				$name = __( 'Bot Protection', 'wp-simple-firewall' );
				$summary = sprintf( '[DEPRECATED - %s] %s',
					'Please use the newer AntiBot setting',
					__( 'Protect WP Login From Automated Login Attempts By Bots', 'wp-simple-firewall' )
				);
				$desc = [
					__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'ON', 'wp-simple-firewall' ) )
				];
				break;

			case 'antibot_form_ids' :
				$name = __( 'AntiBot Forms', 'wp-simple-firewall' );
				$summary = sprintf( '%s %s',
					'[DEPRECATED - Please use the newer AntiBot setting]',
					__( 'Enter The Selectors Of The 3rd Party Login Forms For Use With AntiBot JS', 'wp-simple-firewall' )
				);
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
				$desc = [
					__( 'WordPress will process only ONE account access attempt per number of seconds specified.', 'wp-simple-firewall' ),
					__( 'Zero (0) turns this off.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ),
						$this->opts()->getOptDefault( 'login_limit_interval' ) )
				];
				break;

			case 'enable_user_register_checking' :
				$name = __( 'User Registration', 'wp-simple-firewall' );
				$summary = __( 'Apply Brute Force Protection To User Registration And Lost Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'When enabled, settings in this section will also apply to new user registration and users trying to reset passwords.', 'wp-simple-firewall' ) ];
				break;

			case 'enable_passkeys' :
				$name = __( 'Allow Passkeys', 'wp-simple-firewall' );
				$summary = __( 'Allow Passkey Registration', 'wp-simple-firewall' );
				$desc = [
					__( 'Allow users to register Passkeys & FIDO2 devices to complete their WordPress login.', 'wp-simple-firewall' ),
					__( "Passkeys include Windows Hello, compatible Fingerprint readers, and most recent Yubikey & Google Titan devices.", 'wp-simple-firewall' ),
				];
				break;

			case 'enable_yubikey' :
				$name = __( 'Allow Yubikey OTP', 'wp-simple-firewall' );
				$summary = __( 'Allow Yubikey Registration For One Time Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'Combined with your Yubikey API details this will form the basis of your Yubikey Authentication', 'wp-simple-firewall' ) ];
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
				$desc = [
					__( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.', 'wp-simple-firewall' ),
					__( 'Please review the info link on how to get your own Yubikey App ID and API Key.', 'wp-simple-firewall' )
				];
				break;

			case 'yubikey_unique_keys' :
				$name = __( 'Yubikey Unique Keys', 'wp-simple-firewall' );
				$summary = __( 'This method for Yubikeys is no longer supported. Please see your user profile', 'wp-simple-firewall' );
				$desc = [
					sprintf( '<strong>%s: %s</strong>', __( 'Format', 'wp-simple-firewall' ), 'Username,Yubikey' ),
					__( 'Provide Username<->Yubikey Pairs that are usable for this site.', 'wp-simple-firewall' ),
					__( 'If a Username is not assigned a Yubikey, Yubikey Authentication is OFF for that user.', 'wp-simple-firewall' ),
					__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.', 'wp-simple-firewall' ),
				];
				break;

			case 'text_imahuman' :
				$name = __( 'GASP Checkbox Text', 'wp-simple-firewall' );
				$summary = __( 'The User Message Displayed Next To The GASP Checkbox', 'wp-simple-firewall' );
				$desc = [
					__( "You can change the text displayed to the user beside the checkbox if you need a custom message.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $mod->getTextOptDefault( 'text_imahuman' ) )
				];
				break;

			case 'text_pleasecheckbox' :
				$name = __( 'GASP Alert Text', 'wp-simple-firewall' );
				$summary = __( "The Message Displayed If The User Doesn't Check The Box", 'wp-simple-firewall' );
				$desc = [
					__( "You can change the text displayed to the user in the alert message if they don't check the box.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $mod->getTextOptDefault( 'text_pleasecheckbox' ) )
				];
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
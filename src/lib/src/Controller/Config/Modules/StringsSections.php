<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class StringsSections {

	use PluginControllerConsumer;

	public function getFor( string $key ) :array {
		$con = self::con();
		$pluginName = $con->getHumanName();
		$modStrings = new StringsModules();

		switch ( $key ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::ACTIVITY )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Activity Log is designed so you can look back on events and analyse what happened and what may have gone wrong.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Activity Log', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_at_file' :
				$title = __( 'Log To File', 'wp-simple-firewall' );
				$short = __( 'Log To File', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the DB Activity Log.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_localdb' :
				$title = __( 'Log To DB', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the file-based Activity Log.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Log To DB', 'wp-simple-firewall' );
				break;

			case 'section_enable_plugin_feature_automatic_updates_control' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::AUTOUPDATES )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic Updates', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_automatic_updates_for_wordpress_components' :
				$title = __( 'Automatic Updates For WordPress Components', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Control how automatic updates for each WordPress component is handled.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'You should at least allow minor updates for the WordPress core.', 'wp-simple-firewall' ) )
				];
				$short = __( 'WordPress Components', 'wp-simple-firewall' );
				break;
			case 'section_options' :
				$title = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$short = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Make adjustments to how automatic updates are handled on your site.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_enable_plugin_feature_spam_comments_protection_filter' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Comments SPAM Protection', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Comments Filter can block 100% of automated spam bots and also offer the option to analyse human-generated spam.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Comments Filter', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_bot_comment_spam_common' :
				$short = __( 'Common Settings', 'wp-simple-firewall' );
				$title = __( 'Common Settings For All SPAM Scanning', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Settings that apply to all comment SPAM scanning.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_bot_comment_spam_protection_filter' :
				$title = sprintf( __( '%s Comment SPAM Protection', 'wp-simple-firewall' ), __( 'Automatic Bot', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks 100% of all automated bot-generated comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Bot SPAM', 'wp-simple-firewall' );
				break;
			case 'section_human_spam_filter' :
				$title = sprintf( __( '%s Comment SPAM Protection', 'wp-simple-firewall' ), __( 'Human', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					__( 'This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis.', 'wp-simple-firewall' )
				];
				$short = __( 'Human SPAM', 'wp-simple-firewall' );
				break;

			case 'section_enable_plugin_feature_wordpress_firewall' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::FIREWALL )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Firewall is designed to analyse data sent to your website and block any requests that appear to be malicious.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Firewall', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_firewall_blocking_options' :
				$title = __( 'Firewall Blocking Options', 'wp-simple-firewall' );
				$summary = [
					__( 'Here you choose what kind of malicious data to scan for.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( 'Turn on as many options here as you can.', 'wp-simple-firewall' ) )
					.' '.__( 'If you find an incompatibility or something stops working, un-check 1 option at a time until you find the problem or review the Activity Log.', 'wp-simple-firewall' ),
				];
				$short = __( 'Firewall Blocking', 'wp-simple-firewall' );
				break;
			case 'section_choose_firewall_block_response' :
				$title = __( 'Choose Firewall Block Response', 'wp-simple-firewall' );
				$summary = [
					__( 'Here you choose how the plugin will respond when it detects malicious data.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Choose the option "%s".', 'wp-simple-firewall' ), __( 'Die With Message', 'wp-simple-firewall' ) ) )
				];
				$short = __( 'Firewall Response', 'wp-simple-firewall' );
				break;
			case 'section_whitelist' :
				$title = __( 'Whitelists - Pages, Parameters, and Users that bypass the Firewall', 'wp-simple-firewall' );
				$summary = [
					__( 'In principle you should not need to whitelist anything or anyone unless you have discovered a collision with another plugin.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Do not whitelist anything unless you are confident in what you are doing.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Whitelist', 'wp-simple-firewall' );
				break;

			case 'section_scan_options' :
				$title = __( 'Scan Options', 'wp-simple-firewall' );
				$short = __( 'Scan Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Set how frequently the Hack Guard scans will run.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_enable_plugin_feature_hack_protection_tools' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::SCANS )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Hack Guard is a set of tools to warn you and protect you against hacks on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Hack Guard', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_scan_wpv' :
				$short = sprintf( '%s, %s, %s', __( 'Vulnerabilities', 'wp-simple-firewall' ),
					__( 'Plugins', 'wp-simple-firewall' ), __( 'Themes', 'wp-simple-firewall' ) );
				$title = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Vulnerabilities Scanner', 'wp-simple-firewall' ) ) ),
					__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.', 'wp-simple-firewall' )
				];
				break;
			case 'section_file_guard' :
				$short = __( 'File Scans and Malware', 'wp-simple-firewall' );
				$title = __( 'File Scanning and Malware Protection', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor WordPress files and protect against malicious intrusion and hacking.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $title ) )
				];
				break;

			case 'section_enable_plugin_feature_headers' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::HEADERS )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_security_headers' :
				$title = __( 'Advanced Security Headers', 'wp-simple-firewall' );
				$short = __( 'Security Headers', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_content_security_policy' :
				$title = __( 'Content Security Policy', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restrict the sources and types of content that may be loaded and processed by visitor browsers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Content Security Policy', 'wp-simple-firewall' );
				break;

			case 'section_enable_plugin_feature_ips' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::IPS )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'IP Manager', 'wp-simple-firewall' ) ) )
					.'<br />'.__( 'You should also carefully review the automatic black list settings.', 'wp-simple-firewall' )
				];
				break;
			case 'section_auto_black_list' :
				$title = __( 'Auto IP Blocking Rules', 'wp-simple-firewall' );
				$short = __( 'Auto Blocking Rules', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of offenses.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) ) ),
					__( "Think of 'offenses' as just a counter for the number of times a visitor does something bad.", 'wp-simple-firewall' )
					.' '.sprintf(
						__( 'When the counter reaches the limit below (default: %s), %s will block that IP completely.', 'wp-simple-firewall' ),
						$con->opts->optDefault( 'transgression_limit' ),
						$pluginName
					)
				];
				break;
			case 'section_bot_behaviours':
				$short = __( 'Bot Behaviours', 'wp-simple-firewall' );
				$title = __( 'Detect Behaviours Common To Bots', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Detect characteristics and behaviour commonly associated with illegitimate bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_antibot':
				$short = __( 'AntiBot System', 'wp-simple-firewall' );
				$title = __( 'AntiBot System', 'wp-simple-firewall' );
				$summary = [];
				break;
			case 'section_crowdsec':
				$short = __( 'CrowdSec', 'wp-simple-firewall' );
				$title = __( 'CrowdSec Community IP Reputation Database', 'wp-simple-firewall' );
				$summary = [];
				break;

			case 'section_integrations':
				$short = __( 'Integrations', 'wp-simple-firewall' );
				$title = __( 'Built-In Shield Integrations', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Shield can automatically integrate with 3rd party plugins.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Only enable the integrations you require.", 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_spam':
				$short = __( 'Contact Form SPAM Checking', 'wp-simple-firewall' );
				$title = __( 'Contact Form SPAM Checking', 'wp-simple-firewall' );
				$summary = [
					__( 'Select The Form Providers That Should Be Checked For SPAM', 'wp-simple-firewall' )
				];
				break;
			case 'section_user_forms':
				$short = __( 'User Forms Bot Checking', 'wp-simple-firewall' );
				$title = __( 'User Forms Bot Checking', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Shield can automatically protect 3rd party login and registration forms against Bots.", 'wp-simple-firewall' ),
						__( "It uses our exclusive AntiBot Detection Engine to reliably identify bots.", 'wp-simple-firewall' )
					),
					sprintf( '%s - %s (%s)', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Only enable the integrations you require.", 'wp-simple-firewall' ),
						__( "WordPress is always enabled.", 'wp-simple-firewall' )
					),
				];
				break;

			case 'section_enable_plugin_feature_wordpress_lockdown' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::LOCKDOWN )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Lockdown', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_apixml' :
				$title = __( 'API & XML-RPC', 'wp-simple-firewall' );
				$short = __( 'API & XML-RPC', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown certain core WordPress system features.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This depends on your usage and needs for certain WordPress functions and features.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_permission_access_options' :
				$title = __( 'Permissions and Access Options', 'wp-simple-firewall' );
				$short = __( 'Permissions', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control of certain WordPress permissions.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Only enable SSL if you have a valid certificate installed.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_wordpress_obscurity_options' :
				$title = __( 'WordPress Obscurity Options', 'wp-simple-firewall' );
				$short = __( 'Obscurity', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Obscures certain WordPress settings from public view.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Obscurity is not true security and so these settings are down to your personal tastes.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_enable_plugin_feature_login_protection' :
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::LOGIN )[ 'name' ] );
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Login Guard blocks all automated and brute force attempts to log in to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_rename_wplogin' :
				$title = __( 'Hide WordPress Login Page', 'wp-simple-firewall' );
				$short = __( 'Hide Login', 'wp-simple-firewall' );
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
				$short = sprintf( '%s :: %s', __( '2FA', 'wp-simple-firewall' ), __( 'General' ) );
				$title = __( '2FA General Configuration', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					__( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' )
				];
				break;
			case 'section_2fa_email' :
				$short = sprintf( '%s :: %s', __( '2FA', 'wp-simple-firewall' ), __( 'Email' ) );
				$title = __( '2FA by Email', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using email-based one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_2fa_otp' :
				$short = sprintf( '%s :: %s', __( '2FA', 'wp-simple-firewall' ), __( 'OTP' ) );
				$title = __( '2FA One-Time Passwords', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site using Google Authenticator one-time-passwords.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You may combine multiple authentication factors for increased security.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_2fa_passkeys' :
				$short = sprintf( '%s :: %s', __( '2FA', 'wp-simple-firewall' ), __( 'Passkeys' ) );
				$title = __( '2FA with Passkeys (WebAuthn)', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies user login with Passkeys/Authenticators via WebAuthn.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_brute_force_login_protection' :
				$title = __( 'Brute Force Login Protection', 'wp-simple-firewall' );
				$short = __( 'Brute Force Protection', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks brute force hacking attacks against your login and registration pages.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_global_security_options' :
				$title = __( 'Global Security Plugin Disable', 'wp-simple-firewall' );
				$short = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), $pluginName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Use this option to completely disable all active Shield Protection.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_defaults' :
				$title = __( 'Plugin Defaults', 'wp-simple-firewall' );
				$short = __( 'Plugin Defaults', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Important default settings used throughout the plugin.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_alerts' :
				$title = __( 'Instant Alerts', 'wp-simple-firewall' );
				$short = __( 'Instant Alerts', 'wp-simple-firewall' );
				$summary = [
					__( 'Receive instant alerts from the plugin for important events.', 'wp-simple-firewall' ),
				];
				break;
			case 'section_reporting' :
				$title = __( 'Reports', 'wp-simple-firewall' );
				$short = __( 'Reports', 'wp-simple-firewall' );
				$summary = [
					__( 'Receive regular reports from the plugin summarising important events.', 'wp-simple-firewall' ),
					sprintf( 'Your reporting email address is: %s',
						'<code>'.self::con()->comps->opts_lookup->getReportEmail().'</code>' )
					.' '.
					sprintf( '<br/><a href="%s" class="fw-bolder">%s</a>',
						self::con()->plugin_urls->modCfgOption( 'block_send_email_address' ),
						__( 'Update reporting email address', 'wp-simple-firewall' )
					),
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Choose the most appropriate frequency to receive alerts from Shield according to your schedule.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_importexport' :
				$title = sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) );
				$short = sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically import options, and deploy configurations across your entire network.', 'wp-simple-firewall' ) ),
					__( 'This is a Pro-only feature.', 'wp-simple-firewall' ),
				];
				break;

			case 'section_enable_admin_access_restriction' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ) ),
					__( 'You need to also enter a new Security PIN to enable this feature.', 'wp-simple-firewall' ),
				];
				break;
			case 'section_security_admin_settings' :
				$title = __( 'Security Admin Restriction Settings', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$short = __( 'Security Admin Settings', 'wp-simple-firewall' );
				break;
			case 'section_admin_access_restriction_areas' :
				$title = __( 'Security Admin Restriction Zones', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$short = __( 'Access Restriction Zones', 'wp-simple-firewall' );
				break;
			case 'section_whitelabel' :
				$title = __( 'White Label', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Rename and re-brand the %s plugin for your client site installations.', 'wp-simple-firewall' ),
							$pluginName )
					),
					sprintf( '%s - %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'The Security Admin system must be active for these settings to apply.', 'wp-simple-firewall' ),
							$pluginName )
					)
				];
				$short = __( 'White Label', 'wp-simple-firewall' );
				break;

			case 'section_enable_plugin_feature_traffic' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::TRAFFIC )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and review all requests to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Required only if you need to review and investigate and monitor requests to your site', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_traffic_options' :
				$short = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				$title = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Traffic Logging system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;
			case 'section_traffic_limiter' :
				$title = __( 'Brute Force Traffic Rate Limiting', 'wp-simple-firewall' );
				$short = __( 'Traffic Rate Limiting', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Prevents excessive requests from a single visitor.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), sprintf( __( 'This feature is only available while the Traffic Logger is active.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Use this feature with care.', 'wp-simple-firewall' ) )
					.' '.__( 'You could block legitimate visitors who load too many pages in quick succession on your site.', 'wp-simple-firewall' )
				];
				break;

			case 'section_enable_plugin_feature_user_accounts_management' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modStrings->getFor( EnumModules::USERS )[ 'name' ] );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_passwords' :
				$title = __( 'Password Policies', 'wp-simple-firewall' );
				$short = __( 'Password Policies', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Have full control over passwords used by users on the site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				break;
			case 'section_admin_login_notification' :
				$title = __( 'Admin Login Notification', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Notifications', 'wp-simple-firewall' );
				break;
			case 'section_user_session_management' :
				$title = __( 'User Session Management', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$short = __( 'Session Options', 'wp-simple-firewall' );
				break;
			case 'section_user_reg' :
				$short = __( 'User Registrations', 'wp-simple-firewall' );
				$title = __( 'User Registrations', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Control user registration and prevent SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;
			case 'section_suspend' :
				$short = __( 'User Suspension', 'wp-simple-firewall' );
				$title = __( 'Automatic And Manual User Suspension', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically suspends accounts to prevent login by certain users.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				$def = $con->cfg->configuration->sections[ $key ];
				$title = __( $def[ 'title' ] ?? 'No Title', 'wp-simple-firewall' );
				$short = __( $def[ 'title_short' ] ?? 'No Title', 'wp-simple-firewall' );
				$summary = $def[ 'summary' ] ?? [];
				break;
		}

		return [
			'title'       => $title,
			'title_short' => $short,
			'summary'     => $summary,
		];
	}
}
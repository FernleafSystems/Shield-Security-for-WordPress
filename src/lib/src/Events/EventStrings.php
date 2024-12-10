<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventStrings {

	use PluginControllerConsumer;

	private $strings = null;

	public function for( string $event ) :array {
		return $this->all()[ $event ] ?? [];
	}

	public function all() :array {
		return $this->strings ?? $this->strings = $this->theStrings();
	}

	private function theStrings() :array {
		return [
			'reg_email_invalid'            => [
				'name'  => __( 'Invalid User Email Registration', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Detected user registration with invalid email address ({{email}}).', 'wp-simple-firewall' ),
					__( 'Email verification test that failed: {{reason}}' ),
				],
			],
			'password_expired'             => [
				'name'  => __( 'Password Expired', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Forcing user ({{user_login}}) to update expired password.', 'wp-simple-firewall' ),
				],
			],
			'password_policy_force_change' => [
				'name'  => __( 'Forced Password Change', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Forcing user ({{user_login}}) to update password that fails to meet policies.', 'wp-simple-firewall' ),
				],
			],
			'password_policy_block'        => [
				'name'  => __( 'Password Change Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Blocked attempted password update that failed policy requirements.', 'wp-simple-firewall' ),
				],
			],
			'session_notfound'             => [
				'name'  => __( 'Session Not Found', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Valid user session could not be found ({{user_login}}).', 'wp-simple-firewall' ),
					__( 'Logging out.', 'wp-simple-firewall' )
				],
			],
			'session_expired'              => [
				'name'  => __( 'Session Expired', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User session has expired ({{user_login}}).', 'wp-simple-firewall' ),
					__( 'Logging out.', 'wp-simple-firewall' )
				],
			],
			'session_idle'                 => [
				'name'  => __( 'Session Idle', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User session has expired due to inactivity ({{user_login}}).', 'wp-simple-firewall' ),
					__( 'Logging out.', 'wp-simple-firewall' )
				],
			],
			'session_lock'                 => [
				'name'  => __( 'Session Locked', 'wp-simple-firewall' ),
				'audit' => [
					__( "Core properties of an established user session ({{user_login}}) have changed.", 'wp-simple-firewall' ),
					__( 'Logging out.', 'wp-simple-firewall' )
				],
			],
			'user_hard_suspended'          => [
				'name'  => __( 'User Manually Suspended', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" suspended by admin.', 'wp-simple-firewall' ),
				],
			],
			'user_hard_unsuspended'        => [
				'name'  => __( 'User Manually Unsuspended', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" unsuspended by admin.', 'wp-simple-firewall' ),
				],
			],
			'request_limit_exceeded'       => [
				'name'  => __( 'Rate Limit Exceeded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Rate limit ({{count}}) was exceeded with {{requests}} requests within {{span}} seconds.', 'wp-simple-firewall' ),
				],
			],
			'key_success'                  => [
				'name'  => __( 'Security PIN Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Security PIN authentication successful.', 'wp-simple-firewall' ),
				],
			],
			'key_fail'                     => [
				'name'  => __( 'Security PIN Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Security PIN authentication failed.', 'wp-simple-firewall' ),
				],
			],
			'attempt_deactivation'         => [
				'name'  => __( 'Unauthorized Deactivation Attempt', 'wp-simple-firewall' ),
				'audit' => [
					sprintf( __( 'An attempt to deactivate the %s plugin by a non-admin was intercepted.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				],
			],
			'debug_log'                    => [
				'name'  => __( 'Custom Debug', 'wp-simple-firewall' ),
				'audit' => [
					'{{message}}',
				],
			],
			'plugin_option_changed'        => [
				'name'  => __( 'Plugin Option Changed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin option "{{name}}" ({{key}}) was updated to "{{value}}".', 'wp-simple-firewall' ),
				]
			],
			'site_blockdown_started'       => [
				'name'  => __( 'Site Lockdown Started', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Site was placed into lockdown by {{user_login}}.', 'wp-simple-firewall' ),
				]
			],
			'site_blockdown_ended'         => [
				'name'  => __( 'Site Lockdown Started', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Site was taken out of lockdown.', 'wp-simple-firewall' ),
				]
			],
			'frontpage_load'               => [
				'name'  => sprintf( '%s: %s', __( 'Loaded', 'wp-simple-firewall' ),
					__( 'Front Page', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Front page loaded', 'wp-simple-firewall' ),
				],
			],
			'loginpage_load'               => [
				'name'  => sprintf( '%s: %s', __( 'Loaded', 'wp-simple-firewall' ),
					__( 'Login Page', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Login page loaded', 'wp-simple-firewall' ),
				],
			],
			'recaptcha_success'            => [
				'name'  => __( 'CAPTCHA Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( 'CAPTCHA test successful.', 'wp-simple-firewall' ),
				],
			],
			'recaptcha_fail'               => [
				'name'  => __( 'CAPTCHA Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'CAPTCHA test failed.', 'wp-simple-firewall' ),
				],
			],
			'test_cron_run'                => [
				'name'  => __( 'Test Cron Run', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Test WP Cron ran successfully.', 'wp-simple-firewall' ),
				],
			],
			'import_notify_sent'           => [
				'name'  => __( 'Import Notify Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Sent notifications to whitelisted sites for required options import.', 'wp-simple-firewall' ),
				],
			],
			'import_notify_received'       => [
				'name'  => __( 'Import Notify Received', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Scheduled an automatic import after receiving notice that an options import was required from the master site.', 'wp-simple-firewall' ),
					__( 'Current master site: {{master_site}}', 'wp-simple-firewall' ),
				],
			],
			'options_exported'             => [
				'name'  => __( 'Options Exported', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Options exported to site: {{site}}', 'wp-simple-firewall' ),
				],
			],
			'options_imported'             => [
				'name'  => __( 'Options Imported', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Options imported from site: {{site}}', 'wp-simple-firewall' ),
				],
			],
			'whitelist_site_added'         => [
				'name'  => __( 'Whitelist Site Added', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Site added to export white list: {{site}}', 'wp-simple-firewall' ),
				],
			],
			'whitelist_site_removed'       => [
				'name'  => __( 'Whitelist Site Removed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Site removed from export white list: {{site}}', 'wp-simple-firewall' ),
				],
			],
			'master_url_set'               => [
				'name'  => __( 'Whitelist Site Removed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Master Site URL set: {{site}}', 'wp-simple-firewall' ),
				],
			],
			'antibot_pass'                 => [
				'name'  => __( 'AntiBot Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Request passed the AntiBot Test with a Visitor Score of "{{score}}" (minimum score: {{minimum}}).', 'wp-simple-firewall' ),
				],
			],
			'antibot_fail'                 => [
				'name'  => __( 'AntiBot Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Request failed the AntiBot Test with a Visitor Score of "{{score}}" (minimum score: {{minimum}}).', 'wp-simple-firewall' ),
				],
			],
			'report_generated'             => [
				'name'  => __( 'Report Generated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Report Generated.', 'wp-simple-firewall' ),
					__( 'Type: {{type}}; Interval: {{interval}};', 'wp-simple-firewall' ),
				],
			],
			'report_sent'                  => [
				'name'  => __( 'Report Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Report Sent (via {{medium}}).', 'wp-simple-firewall' ),
				],
			],
			'session_start'                => [
				'name'  => __( 'Session Started', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session started for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate'            => [
				'name'  => __( 'Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session terminated.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate_current'    => [
				'name'  => __( 'Current Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Current session terminated for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'login_success'                => [
				'name'  => __( 'Login Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Login successful.', 'wp-simple-firewall' ),
				],
			],
			'botbox_fail'                  => [
				'name'  => __( 'BotBox Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" attempted "{{action}}" but Bot checkbox was not found.', 'wp-simple-firewall' ),
				],
			],
			'cooldown_fail'                => [
				'name'  => __( 'Cooldown Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Login/Register request triggered cooldown and was blocked.', 'wp-simple-firewall' )
				],
			],
			'honeypot_fail'                => [
				'name'  => __( 'Honeypot Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" attempted {{action}} but they were caught by the honeypot.', 'wp-simple-firewall' )
				],
			],
			'2fa_success'                  => [
				'name'  => __( '2FA Login Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Successful 2FA Login Verification', 'wp-simple-firewall' ),
				],
			],
			'2fa_verify_success'           => [
				'name'  => __( '2FA Verify Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" verified their identity using "{{method}}".', 'wp-simple-firewall' )
				],
			],
			'2fa_verify_fail'              => [
				'name'  => __( '2FA Verify Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" failed to verify their identity using "{{method}}".', 'wp-simple-firewall' )
				],
			],
			'2fa_nonce_verify_fail'        => [
				'name'  => __( '2FA Nonce Verify Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to verify a 2FA login "{{user_login}}" using an invalid nonce.', 'wp-simple-firewall' )
				],
			],
			// todo rename to block_login
			'login_block'                  => [
				'name'  => __( 'Login Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User login request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_register'               => [
				'name'  => __( 'Registration Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User registration request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_lostpassword'           => [
				'name'  => __( 'Lost Password Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User lost password request blocked.', 'wp-simple-firewall' ),
				],
			],
			'block_checkout'               => [
				'name'  => __( 'Checkout Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User attempting checkout request blocked.', 'wp-simple-firewall' ),
				],
			],
			'hide_login_url'               => [
				'name'  => __( 'Hidden Login URL Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Redirecting wp-login due to hidden login URL', 'wp-simple-firewall' ),
				],
			],
			'block_anonymous_restapi'      => [
				'name'  => sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), __( 'Anonymous REST API' ) ),
				'audit' => [
					__( 'Blocked Anonymous API Access through "{{namespace}}" namespace.', 'wp-simple-firewall' ),
				],
			],
			'block_author_fishing'         => [
				'name'  => sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), __( 'Author Fishing' ) ),
				'audit' => [
					__( 'Blocked Author Discovery via username fishing technique.', 'wp-simple-firewall' ),
				],
			],
			'block_xml'                    => [
				'name'  => sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), __( 'XML-RPC' ) ),
				'audit' => [
					__( 'XML-RPC Request Blocked.', 'wp-simple-firewall' ),
				],
			],
			'lic_check_success'            => [
				'name'  => __( 'License Check Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check succeeded.', 'wp-simple-firewall' ),
				],
			],
			'lic_check_fail'               => [
				'name'  => __( 'License Check Failed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check request failed.', 'wp-simple-firewall' ),
					__( 'Failure Type: {{type}}', 'wp-simple-firewall' ),
				],
			],
			'lic_fail_email'               => [
				'name'  => __( 'License Failure Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check failed. Sending Warning Email.', 'wp-simple-firewall' ),
				],
			],
			'lic_fail_deactivate'          => [
				'name'  => __( 'License Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A valid license could not be found - Deactivating Pro.', 'wp-simple-firewall' ),
				],
			],
			'spam_form_pass'               => [
				'name'  => __( 'SPAM Check Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission passed SPAM check.', 'wp-simple-firewall' ),
				],
			],
			'spam_form_fail'               => [
				'name'  => __( 'SPAM Check Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission failed SPAM check.', 'wp-simple-firewall' ),
				],
			],
			'user_form_bot_pass'           => [
				'name'  => __( 'User Bot Check Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission for form "{{action}}" with username "{{username}}" passed Bot check.', 'wp-simple-firewall' ),
				],
			],
			'user_form_bot_fail'           => [
				'name'  => __( 'User Bot Check Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( '"{{form_provider}}" submission for form "{{action}}" with username "{{username}}" failed Bot check.', 'wp-simple-firewall' ),
				],
			],
			'suresend_fail'                => [
				'name'  => __( 'SureSend Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Failed to send email (type: {{slug}}) to "{{email}}" using SureSend.', 'wp-simple-firewall' ),
				],
			],
			'suresend_success'             => [
				'name'  => __( 'SureSend Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Successfully sent email (type: {{slug}}) to "{{email}}" using SureSend.', 'wp-simple-firewall' ),
				],
			],
			'ade_check_option_disabled'    => [
				'name'  => __( 'silentCAPTCHA Check Invalid (Module)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A silentCAPTCHA Bot Check was performed on a visitor but the Bot-blocking option is disabled in settings.', 'wp-simple-firewall' ),
					__( "The visitor was allowed to pass the checks since they couldn't be applied.", 'wp-simple-firewall' ),
				],
			],
			'conn_kill'                    => [
				'name'  => __( 'Connection Killed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Visitor found on the Block List and their connection was killed.', 'wp-simple-firewall' ),
				],
			],
			'conn_kill_crowdsec'           => [
				'name'  => __( 'CrowdSec: Connection Killed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Visitor found on the CrowdSec Block List and their request was killed.', 'wp-simple-firewall' ),
				],
			],
			'conn_not_kill_high_rep'       => [
				'name'  => __( 'Connection Not Killed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'IP address has a high reputation so connection allowed.', 'wp-simple-firewall' ),
				],
			],
			'ip_offense'                   => [
				'name'  => __( 'Offense Triggered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Auto Black List offenses counter was incremented from {{from}} to {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'ip_blocked'                   => [
				'name'  => __( 'IP Blocked', 'wp-simple-firewall' ),
				'audit' => [
					__( 'IP blocked after incrementing offenses from {{from}} to {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'ip_unblock'                   => [
				'name'  => __( 'IP Unblocked', 'wp-simple-firewall' ),
				'audit' => [
					__( '{{ip}} removed from block list ({{type}}).', 'wp-simple-firewall' ),
				],
			],
			'ip_unblock_auto'              => [
				'name'  => __( 'IP Unblocked By Visitor', 'wp-simple-firewall' ),
				'audit' => [
					__( "Visitor unblocked their IP address '{{ip}}' using the '{{method}}' method.", 'wp-simple-firewall' ),
				],
			],
			'ip_unblock_flag'              => [
				'name'  => __( 'IP Unblocked (Flag File)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' removed from blacklist using 'unblock' file flag.", 'wp-simple-firewall' ),
				],
			],
			'ip_block_auto'                => [
				'name'  => __( 'IP Block List Add (Auto)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' automatically added to block list as an offender.", 'wp-simple-firewall' )
					.' '.__( "The IP may not be blocked yet.", 'wp-simple-firewall' ),
				],
			],
			'ip_block_manual'              => [
				'name'  => __( 'IP Block List Add (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually added to block list.", 'wp-simple-firewall' ),
				],
			],
			'ip_bypass_add'                => [
				'name'  => __( 'IP Bypass List Add (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually added to bypass list.", 'wp-simple-firewall' ),
				],
			],
			'ip_bypass_remove'             => [
				'name'  => __( 'IP Bypass List Removed (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( "IP address '{{ip}}' manually removed from the bypass list.", 'wp-simple-firewall' ),
				],
			],
			'bottrack_notbot'              => [
				'name'  => __( 'silentCAPTCHA Registration', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Visitor registered using silentCAPTCHA.', 'wp-simple-firewall' ),
				],
			],
			'bottrack_404'                 => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ), '404' ),
				'audit' => [
					__( '404 detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_fakewebcrawler'      => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Fake Web Crawler', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Fake Web Crawler detected at "{{path}}".', 'wp-simple-firewall' ),
					__( 'Fake Crawler misrepresented itself as "{{crawler}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_linkcheese'          => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Link Cheese', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Link cheese access detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_loginfailed'         => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Failed Login', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Attempted login failed by user "{{user_login}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_logininvalid'        => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Invalid Username Login', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Attempted login with invalid user "{{user_login}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_useragent'           => [
				/** TODO **/
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Invalid User-Agent', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Invalid user agent detected at "{{useragent}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_xmlrpc'              => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'XML-RPC', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Access to XML-RPC detected at "{{path}}".', 'wp-simple-firewall' ),
				],
			],
			'bottrack_invalidscript'       => [
				'name'  => sprintf( '%s: %s', __( 'Bots', 'wp-simple-firewall' ),
					__( 'Invalid Scripts', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Tried to load an invalid WordPress PHP script "{{script}}".', 'wp-simple-firewall' ),
				],
			],
			'comment_markspam'             => [
				'name'  => __( 'Mark Comment SPAM (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment manually marked as SPAM.', 'wp-simple-firewall' ),
				],
			],
			'comment_unmarkspam'           => [
				'name'  => __( 'Mark Comment Not SPAM (Manual)', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment manually marked as not SPAM.', 'wp-simple-firewall' ),
				],
			],
			'crowdsec_mach_register'       => [
				'name'  => sprintf( '%s: %s', __( 'CrowdSec', 'wp-simple-firewall' ), __( 'Registered Site', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Registered the website with the CrowdSec service.', 'wp-simple-firewall' ),
					sprintf( '%s: {{url}}', __( 'Website URL', 'wp-simple-firewall' ) ),
					sprintf( '%s: {{machine_id}}', __( 'Website ID', 'wp-simple-firewall' ) ),
				],
			],
			'crowdsec_auth_acquire'        => [
				'name'  => sprintf( '%s: %s', __( 'CrowdSec', 'wp-simple-firewall' ), __( 'Acquired Authentication Token', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Acquired authentication token for use with CrowdSec service.', 'wp-simple-firewall' ),
					sprintf( '%s: {{expiration}}', __( 'Expiration', 'wp-simple-firewall' ) ),
				],
			],
			'crowdsec_mach_enroll'         => [
				'name'  => sprintf( '%s: %s', __( 'CrowdSec', 'wp-simple-firewall' ), __( 'Enrolled Site With Console', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Enrolled site with CrowdSec console.', 'wp-simple-firewall' ),
					sprintf( '%s: {{id}}', __( 'Enrollment ID', 'wp-simple-firewall' ) ),
					sprintf( '%s: {{name}}', __( 'Enrollment Name', 'wp-simple-firewall' ) ),
				],
			],
			'crowdsec_decisions_acquired'  => [
				'name'  => sprintf( '%s: %s', __( 'CrowdSec', 'wp-simple-firewall' ), __( 'Reputation Decisions Acquired', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'Downloaded reputation decisions from CrowdSec for scope: {{scope}}.', 'wp-simple-firewall' ),
					sprintf( '%s: {{count_new}}', __( 'New', 'wp-simple-firewall' ) ),
					sprintf( '%s: {{count_deleted}}', __( 'Deleted', 'wp-simple-firewall' ) ),
					sprintf( '%s: {{time_taken}}s', __( 'Time Taken', 'wp-simple-firewall' ) ),
				],
			],
			'crowdsec_signals_pushed'      => [
				'name'  => sprintf( '%s: %s', __( 'CrowdSec', 'wp-simple-firewall' ), __( 'Signals Pushed', 'wp-simple-firewall' ) ),
				'audit' => [
					__( 'IP Reputation signals pushed successfully to CrowdSec.', 'wp-simple-firewall' ),
					sprintf( '%s: {{count}}', __( 'Total Signals', 'wp-simple-firewall' ) ),
				],
			],
			'scan_run'                     => [
				'name'  => __( 'Scan Completed', 'wp-simple-firewall' ),
				'audit' => [
					sprintf( '%s: {{scan}}', __( 'Scan Completed', 'wp-simple-firewall' ) ),
				],
			],
			'scan_item_delete_success'     => [
				'name'  => __( 'Scan Item Delete Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Item found in the scan was deleted.', 'wp-simple-firewall' ),
					__( 'Item deleted: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_item_repair_success'     => [
				'name'  => __( 'Scan Item Repair Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Repaired item found in the scan.', 'wp-simple-firewall' ),
					__( 'Item repaired: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_item_repair_fail'        => [
				'name'  => __( 'Scan Item Repair Failure', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Failed to repair scan item.', 'wp-simple-firewall' ),
					__( 'Failed item: "{{path_full}}"', 'wp-simple-firewall' ),
				],
			],
			'scan_items_found'             => [
				'name'  => __( 'Items Found In Scan', 'wp-simple-firewall' ),
				'audit' => [
					__( '{{scan}}: scan completed and items were discovered.', 'wp-simple-firewall' ),
					sprintf( '%s: %s {{items}}',
						__( 'Note', 'wp-simple-firewall' ),
						__( "These items wont display in results if you've previously marked them as ignored.", 'wp-simple-firewall' )
					),
				],
			],
			'firewall_block'               => [
				'name'  => __( 'Firewall Block', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Request blocked by firewall rule: {{name}}.', 'wp-simple-firewall' ),
					__( 'Rule pattern detected: "{{term}}".', 'wp-simple-firewall' ),
					__( 'The offending request parameter was "{{param}}" with a value of "{{value}}".', 'wp-simple-firewall' ),
				],
			],
			'fw_email_success'             => [
				'name'  => __( 'Firewall Block Email Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Successfully sent Firewall Block email alert to: {{to}}', 'wp-simple-firewall' )
				],
			],
			'fw_email_fail'                => [
				'name'  => __( 'Firewall Block Email Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Failed to send Firewall Block email alert to: {{to}}', 'wp-simple-firewall' )
				],
			],
			'spam_block_antibot'           => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'silentCAPTCHA', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment that failed AntiBot tests.', 'wp-simple-firewall' )
				],
			],
			'spam_block_human'             => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Human', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked human SPAM comment containing suspicious content.', 'wp-simple-firewall' ),
					__( 'Human SPAM filter found "{{word}}" in "{{key}}"', 'wp-simple-firewall' ),
				],
			],
			'spam_block_humanrepeated'     => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Repeated Human SPAM', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked repeated attempts by the same visitor to post multiple SPAM comments.', 'wp-simple-firewall' ),
				],
			],
			'spam_block_cooldown'          => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Cooldown Triggered', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked comment that triggered the Comment Cooldown.', 'wp-simple-firewall' ),
				],
			],
			'spam_block_bot'               => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Bot', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment from Bot.', 'wp-simple-firewall' ),
				],
			],
			'spam_block_recaptcha'         => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'CAPTCHA', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment that failed reCAPTCHA.', 'wp-simple-firewall' ),
				],
			],
			'comment_spam_block'           => [
				'name'  => __( 'Comment SPAM Blocked.', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment SPAM Blocked.', 'wp-simple-firewall' ),
				],
			],
			'db_tables_added'              => [
				'name'  => __( 'DB Tables Added', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Database table(s) added:', 'wp-simple-firewall' ),
					'{{tables}}'
				],
			],
			'db_tables_removed'            => [
				'name'  => __( 'DB Tables Removed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Database table(s) removed:', 'wp-simple-firewall' ),
					'{{tables}}'
				],
			],
			'plugin_activated'             => [
				'name'  => __( 'Plugin Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was activated (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_installed'             => [
				'name'  => __( 'Plugin Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was installed (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_uninstalled'           => [
				'name'  => __( 'Plugin Uninstalled', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was uninstalled (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_deactivated'           => [
				'name'  => __( 'Plugin Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was deactivated (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_upgraded'              => [
				'name'  => __( 'Plugin Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_downgraded'            => [
				'name'  => __( 'Plugin Downgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" downgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_file_edited'           => [
				'name'  => __( 'Plugin File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the plugin file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' )
				],
			],
			'theme_activated'              => [
				'name'  => __( 'Theme Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was activated.', 'wp-simple-firewall' ),
				],
			],
			'theme_installed'              => [
				'name'  => __( 'Theme Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was installed.', 'wp-simple-firewall' )
				],
			],
			'theme_uninstalled'            => [
				'name'  => __( 'Theme Uninstalled', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was uninstalled (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'theme_file_edited'            => [
				'name'  => __( 'Theme File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the theme file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' ),
				],
			],
			'theme_upgraded'               => [
				'name'  => __( 'Theme Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'theme_downgraded'             => [
				'name'  => __( 'Theme Downgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was downgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'core_updated'                 => [
				'name'  => __( 'WP Core Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Core was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'core_reinstalled'             => [
				'name'  => __( 'WordPress Reinstalled', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Core v{{version}} was reinstalled.', 'wp-simple-firewall' ),
				],
			],
			'permalinks_structure'         => [
				'name'  => __( 'Permalinks Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Permalinks Structure was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_admin_email'        => [
				'name'  => __( 'WP Site Admin Email', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site admin email address was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_blogdescription'    => [
				'name'  => __( 'WP Site Tagline', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site tagline was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_blogname'           => [
				'name'  => __( 'WP Site title', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site title changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_default_role'       => [
				'name'  => __( 'WP Default User Role', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The default role for new users was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_home'               => [
				'name'  => __( 'Home URL Changed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The home URL was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_siteurl'            => [
				'name'  => __( 'Site URL Changed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The site URL was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_users_can_register' => [
				'name'  => __( 'WP User Registration', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The option to allow anyone to register on the site was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'post_deleted'                 => [
				'name'  => __( 'Post Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Post entitled "{{title}}" was permanently deleted from trash.', 'wp-simple-firewall' )
				],
			],
			'post_trashed'                 => [
				'name'  => __( 'Post Trashed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was trashed.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_recovered'               => [
				'name'  => __( 'Post Recovered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was recovered from trash.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated'                 => [
				'name'  => __( 'Post Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_created'                 => [
				'name'  => __( 'Post Created', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was created.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_published'               => [
				'name'  => __( 'Post Published', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was published.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_unpublished'             => [
				'name'  => __( 'Post Unpublished', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was unpublished.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_content'         => [
				'name'  => __( 'Post Content Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Content for Post ID {{post_id}} updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_title'           => [
				'name'  => __( 'Post Title Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Title for Post ID {{post_id}} updated from "{{title_old}}" to "{{title_new}}".', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_slug'            => [
				'name'  => __( 'Post Slug Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Slug for Post ID {{post_id}} updated from "{{slug_old}}" to "{{slug_new}}".', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'user_login'                   => [
				'name'  => __( 'User Login', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted user login by "{{user_login}}" was successful.', 'wp-simple-firewall' ),
				],
			],
			'user_registered'              => [
				'name'  => __( 'User Registered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'New WordPress user registered.', 'wp-simple-firewall' ),
					__( 'New username is "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted'                 => [
				'name'  => __( 'User Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress user deleted.', 'wp-simple-firewall' ),
					__( 'Username was "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_promoted'                => [
				'name'  => __( 'User Promoted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" promoted to administrator role.', 'wp-simple-firewall' ),
				],
			],
			'user_demoted'                 => [
				'name'  => __( 'User Demoted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" demoted from administrator role.', 'wp-simple-firewall' ),
				],
			],
			'user_email_updated'           => [
				'name'  => __( 'User Email Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Email updated for user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'user_password_updated'        => [
				'name'  => __( 'User Password Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Password updated for user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'user_deleted_reassigned'      => [
				'name'  => __( 'User Deleted And Reassigned', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Deleted user posts were reassigned to user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'email_attempt_send'           => [
				'name'  => __( 'Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'There was an attempt to send an email using the "wp_mail" function.', 'wp-simple-firewall' ),
					__( "This log entry doesn't mean it was sent or received successfully, but only that an attempt was made.", 'wp-simple-firewall' ),
					__( 'It was sent to "{{to}}" with the subject "{{subject}}".', 'wp-simple-firewall' ),
					"CC/BCC Recipients: {{cc}} / {{bcc}}",
					__( 'The "wp_mail" function was called from the file "{{bt_file}}" on line {{bt_line}}.', 'wp-simple-firewall' )
				],
			],
			'user_login_app'               => [
				'name'  => __( 'User Login By App Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted login by "{{user_login}}" using application password was successful.', 'wp-simple-firewall' ),
				],
			],
			'app_pass_created'             => [
				'name'  => __( 'APP Password Created', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A new application password ({{app_pass_name}}) was created for user {{user_login}}.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_email'            => [
				'name'  => __( 'APP Password Auth - Invalid Email', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid email.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_username'         => [
				'name'  => __( 'APP Password Auth - Invalid Username', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid username.', 'wp-simple-firewall' ),
				],
			],
			'app_incorrect_password'       => [
				'name'  => __( 'Incorrect APP Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate with incorrect App Password.', 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled'       => [
				'name'  => __( 'App Passwords Disabled', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled.", 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled_user'  => [
				'name'  => __( 'App Passwords Disabled For User', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled for the user.", 'wp-simple-firewall' ),
				],
			],
			'comment_created'              => [
				'name'  => __( 'New Comment', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} with status '{{status}}' was newly created on Post ID {{post_id}}.", 'wp-simple-firewall' ),
				],
			],
			'comment_deleted'              => [
				'name'  => __( 'Comment Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} (on Post ID {{post_id}}) with status '{{status}}' was permanently deleted.", 'wp-simple-firewall' ),
				],
			],
			'comment_status_updated'       => [
				'name'  => __( 'Comment Status Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} (on Post ID {{post_id}}) changed status from '{{status_old}}' to '{{status_new}}'.", 'wp-simple-firewall' ),
				],
			],
		];
	}
}
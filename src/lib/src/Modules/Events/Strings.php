<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $eventKey
	 * @return string
	 */
	public function getEventName( $eventKey ) {
		return $this->getEventNames()[ $eventKey ] ?? '';
	}

	/**
	 * @param bool $auto
	 * @return string[]
	 */
	public function getEventNames( bool $auto = true ) :array {
		$names = [
			'test_cron_run'                => __( 'Test Cron Run', 'wp-simple-firewall' ),
			'import_notify_sent'           => __( 'Import Notify Sent', 'wp-simple-firewall' ),
			'import_notify_received'       => __( 'Import Notify Received', 'wp-simple-firewall' ),
			'options_exported'             => __( 'Options Exported', 'wp-simple-firewall' ),
			'options_imported'             => __( 'Options Imported', 'wp-simple-firewall' ),
			'whitelist_site_added'         => __( 'Whitelist Site Added', 'wp-simple-firewall' ),
			'whitelist_site_removed'       => __( 'Whitelist Site Removed', 'wp-simple-firewall' ),
			'master_url_set'               => __( 'Master Site URL Set', 'wp-simple-firewall' ),
			'recaptcha_success'            => __( 'CAPTCHA Test Success', 'wp-simple-firewall' ),
			'recaptcha_fail'               => __( 'CAPTCHA Test Fail', 'wp-simple-firewall' ),
			'key_success'                  => __( 'Security PIN Authentication Success', 'wp-simple-firewall' ),
			'key_fail'                     => __( 'Security PIN Authentication Failed', 'wp-simple-firewall' ),
			'custom_offense'               => __( 'Custom Offense', 'wp-simple-firewall' ),
			'conn_kill'                    => __( 'Connection Killed', 'wp-simple-firewall' ),
			'ip_offense'                   => __( 'Offense Triggered', 'wp-simple-firewall' ),
			'ip_blocked'                   => __( 'IP Blocked', 'wp-simple-firewall' ),
			'ip_unblock_flag'              => __( 'IP Unblocked Using Flag File', 'wp-simple-firewall' ),
			'ip_block_auto'                => __( 'IP Block Add Auto', 'wp-simple-firewall' ),
			'ip_block_manual'              => __( 'IP Block Add Manual', 'wp-simple-firewall' ),
			'ip_bypass_add'                => __( 'IP Bypass Add', 'wp-simple-firewall' ),
			'ip_bypass_remove'             => __( 'IP Bypass Remove', 'wp-simple-firewall' ),
			'antibot_fail'                 => __( 'Fail AntiBot Test', 'wp-simple-firewall' ),
			'antibot_pass'                 => __( 'Pass AntiBot Test', 'wp-simple-firewall' ),
			'bottrack_404'                 => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				'404'
			),
			'bottrack_fakewebcrawler'      => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Fake Web Crawler', 'wp-simple-firewall' )
			),
			'bottrack_linkcheese'          => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Link Cheese', 'wp-simple-firewall' )
			),
			'bottrack_loginfailed'         => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Failed Login', 'wp-simple-firewall' )
			),
			'bottrack_logininvalid'        => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Invalid Username Login', 'wp-simple-firewall' )
			),
			'bottrack_useragent'           => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Invalid User-Agent', 'wp-simple-firewall' )
			),
			'bottrack_xmlrpc'              => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				'XML-RPC'
			),
			'bottrack_invalidscript'       => sprintf( '%s: %s',
				__( 'Bot Detection', 'wp-simple-firewall' ),
				__( 'Invalid Script Load', 'wp-simple-firewall' )
			),
			'apc_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Abandoned Plugin Detected', 'wp-simple-firewall' )
			),
			'mal_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Malware Detected', 'wp-simple-firewall' )
			),
			'ptg_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Modified Plugin/Theme Detected', 'wp-simple-firewall' )
			),
			'ufc_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Unrecognised File Detected', 'wp-simple-firewall' )
			),
			'wcf_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Modified/Missing WP Core File Detected', 'wp-simple-firewall' )
			),
			'wpv_alert_sent'               => sprintf( '%s: %s',
				__( 'Alert Sent', 'wp-simple-firewall' ),
				__( 'Vulnerable Plugin Detected', 'wp-simple-firewall' )
			),
			'apc_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'Abandoned Plugins', 'wp-simple-firewall' )
			),
			'mal_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'Malware', 'wp-simple-firewall' )
			),
			'ptg_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'Plugin/Theme Guard', 'wp-simple-firewall' )
			),
			'ufc_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'Unrecognised Files', 'wp-simple-firewall' )
			),
			'wcf_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'WP Core Files', 'wp-simple-firewall' )
			),
			'wpv_scan_run'                 => sprintf( '%s: %s',
				__( 'Scan Completed', 'wp-simple-firewall' ),
				__( 'Vulnerabilities', 'wp-simple-firewall' )
			),
			'apc_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'Abandoned Plugins', 'wp-simple-firewall' )
			),
			'mal_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'Malware', 'wp-simple-firewall' )
			),
			'ptg_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'Plugin/Theme Guard', 'wp-simple-firewall' )
			),
			'ufc_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'Unrecognised Files', 'wp-simple-firewall' )
			),
			'wcf_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'WP Core Files', 'wp-simple-firewall' )
			),
			'wpv_scan_found'               => sprintf( '%s: %s',
				__( 'Scan Item Discovered', 'wp-simple-firewall' ),
				__( 'Vulnerabilities', 'wp-simple-firewall' )
			),
			'scan_item_delete_success'     => __( 'Scan Item Delete Success', 'wp-simple-firewall' ),
			'scan_item_repair_success'     => __( 'Scan Item Repair Success', 'wp-simple-firewall' ),
			'scan_item_repair_fail'        => __( 'Scan Item Repair Failure', 'wp-simple-firewall' ),
			'2fa_verify_success'           => __( '2FA Verify Success', 'wp-simple-firewall' ),
			'2fa_verify_fail'              => __( '2FA Verify Fail', 'wp-simple-firewall' ),
			'cooldown_fail'                => __( '', 'wp-simple-firewall' ),
			'honeypot_fail'                => __( '', 'wp-simple-firewall' ),
			'botbox_fail'                  => __( '', 'wp-simple-firewall' ),
			'login_block'                  => __( 'Blocked Login', 'wp-simple-firewall' ),
			'hide_login_url'               => __( 'Redirecting wp-login due to hidden login URL', 'wp-simple-firewall' ),
			'2fa_success'                  => __( '', 'wp-simple-firewall' ),
			'check_skip'                   => __( '', 'wp-simple-firewall' ),
			'fw_email_fail'                => __( 'Firewall Block Email Fail', 'wp-simple-firewall' ),
			'fw_email_success'             => __( 'Firewall Block Email Success', 'wp-simple-firewall' ),
			'firewall_block'               => __( 'Firewall Block', 'wp-simple-firewall' ),
			'blockparam_dirtraversal'      => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'Directory Traversal', 'wp-simple-firewall' )
			),
			'blockparam_wpterms'           => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'WordPress Terms', 'wp-simple-firewall' )
			),
			'blockparam_fieldtruncation'   => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'Field Truncation', 'wp-simple-firewall' )
			),
			'blockparam_sqlqueries'        => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'SQL Queries', 'wp-simple-firewall' )
			),
			'blockparam_schema'            => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'Leading Schema', 'wp-simple-firewall' )
			),
			'blockparam_aggressive'        => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'Aggressive Rules', 'wp-simple-firewall' )
			),
			'blockparam_phpcode'           => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'PHP Code', 'wp-simple-firewall' )
			),
			'block_exefile'                => sprintf( '%s: %s',
				__( 'Firewall', 'wp-simple-firewall' ),
				__( 'EXE File Uploads', 'wp-simple-firewall' )
			),
			'session_notfound'             => __( 'Session Not Found', 'wp-simple-firewall' ),
			'session_expired'              => __( 'Session Expired', 'wp-simple-firewall' ),
			'session_idle'                 => __( 'Session Idle', 'wp-simple-firewall' ),
			'session_iplock'               => __( 'Session Locked To IP', 'wp-simple-firewall' ),
			'session_browserlock'          => __( 'Session Locked To Browser', 'wp-simple-firewall' ),
			'session_unverified'           => __( 'Session Unverified', 'wp-simple-firewall' ),
			'password_expired'             => __( 'Password Expired', 'wp-simple-firewall' ),
			'password_policy_force_change' => __( 'Forced Password Change', 'wp-simple-firewall' ),
			'password_policy_block'        => __( 'Password Change Blocked', 'wp-simple-firewall' ),
			'user_hard_suspended'          => __( 'User Hard-Suspended', 'wp-simple-firewall' ),
			'user_hard_unsuspended'        => __( 'User Hard-Unsuspended', 'wp-simple-firewall' ),
			'spam_block_antibot'           => sprintf( '%s: %s',
				__( 'SPAM Blocked', 'wp-simple-firewall' ),
				__( 'AntiBot System', 'wp-simple-firewall' )
			),
			'spam_block_bot'               => sprintf( '%s: %s',
				__( 'SPAM Blocked', 'wp-simple-firewall' ),
				__( 'Bot', 'wp-simple-firewall' )
			),
			'spam_block_recaptcha'         => sprintf( '%s: %s',
				__( 'SPAM Blocked', 'wp-simple-firewall' ),
				__( 'CAPTCHA', 'wp-simple-firewall' )
			),
			'spam_block_human'             => sprintf( '%s: %s',
				__( 'SPAM Blocked', 'wp-simple-firewall' ),
				__( 'Human', 'wp-simple-firewall' )
			),
			'block_anonymous_restapi'      => sprintf( '%s: %s',
				__( 'Blocked', 'wp-simple-firewall' ),
				__( 'Anonymous REST API' )
			),
			'block_xml'                    => sprintf( '%s: %s',
				__( 'Blocked', 'wp-simple-firewall' ),
				__( 'XML-RPC' )
			),
			'session_start'                => __( 'Session Started', 'wp-simple-firewall' ),
			'session_terminate'            => __( 'Session Terminated', 'wp-simple-firewall' ),
			'session_terminate_current'    => __( 'Current Session Terminated', 'wp-simple-firewall' ),
			'plugin_activated'             => __( 'Plugin Activated', 'wp-simple-firewall' ),
			'plugin_deactivated'           => __( 'Plugin Deactivated', 'wp-simple-firewall' ),
			'plugin_upgraded'              => __( 'Plugin Upgraded', 'wp-simple-firewall' ),
			'plugin_file_edited'           => __( 'Plugin File Edited', 'wp-simple-firewall' ),
			'theme_activated'              => __( 'Theme Activated', 'wp-simple-firewall' ),
			'theme_file_edited'            => __( 'Theme File Edited', 'wp-simple-firewall' ),
			'theme_upgraded'               => __( 'Theme Upgraded', 'wp-simple-firewall' ),
			'core_updated'                 => __( 'WP Core Updated', 'wp-simple-firewall' ),
			'permalinks_structure'         => __( 'Permalinks Updated', 'wp-simple-firewall' ),
			'post_deleted'                 => __( 'Post Deleted', 'wp-simple-firewall' ),
			'post_trashed'                 => __( 'Post Trashed', 'wp-simple-firewall' ),
			'post_recovered'               => __( 'Post Recovered', 'wp-simple-firewall' ),
			'post_updated'                 => __( 'Post Updated', 'wp-simple-firewall' ),
			'post_published'               => __( 'Post Published', 'wp-simple-firewall' ),
			'post_unpublished'             => __( 'Post Unpublished', 'wp-simple-firewall' ),
			'user_login'                   => __( 'User Login', 'wp-simple-firewall' ),
			'user_login_app'               => __( 'User Login By App Password', 'wp-simple-firewall' ),
			'user_registered'              => __( 'User Registered', 'wp-simple-firewall' ),
			'user_deleted'                 => __( 'User Deleted', 'wp-simple-firewall' ),
			'user_deleted_reassigned'      => __( 'User Deleted And Reassigned', 'wp-simple-firewall' ),
			'email_attempt_send'           => __( 'Email Sent', 'wp-simple-firewall' ),
			'email_send_invalid'           => __( 'Invalid Email Sent', 'wp-simple-firewall' ),
			'lic_check_success'            => __( 'License Check Success', 'wp-simple-firewall' ),
			'lic_fail_email'               => __( 'License Failure Email', 'wp-simple-firewall' ),
			'lic_fail_deactivate'          => __( 'License Deactivated', 'wp-simple-firewall' ),
		];

		if ( $auto ) {
			foreach ( $names as $key => $name ) {
				if ( empty( $name ) ) {
					$names[ $key ] = ucwords( str_replace( '_', ' ', $key ) );
				}
			}
		}

		return $names;
	}
}
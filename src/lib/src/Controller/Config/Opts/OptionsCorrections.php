<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class OptionsCorrections {

	use PluginControllerConsumer;

	public function run() :void {
		$this->removeDeprecated();
		$this->removeModuleEnablers();
	}

	protected function removeDeprecated() {
		$opts = self::con()->opts;
		if ( $opts->optExists( 'enable_login_gasp_check' ) && $opts->optIs( 'enable_login_gasp_check', 'Y' ) ) {
			$opts->optSet( 'enable_antibot_check', 'Y' );
			$opts->optSet( 'enable_login_gasp_check', 'N' );
		}
		// If the original antibot option was disabled, since we're removing it, we must remove all bot locations.
		if ( $opts->optExists( 'enable_antibot_check' ) && $opts->optIs( 'enable_antibot_check', 'N' ) ) {
			$opts->optSet( 'bot_protection_locations', [] )
				 ->optSet( 'enable_antibot_check', 'Y' );
		}
	}

	protected function removeModuleEnablers() {
		$opts = self::con()->opts;
		if ( $opts->optIs( 'enable_admin_access_restriction', 'N' ) ) {
			$opts->optReset( 'admin_access_key' );
			$opts->optReset( 'sec_admin_users' );
			$opts->optReset( 'enable_admin_access_restriction' );
		}
		if ( $opts->optIs( 'enable_audit_trail', 'N' ) ) {
			$opts->optSet( 'log_level_db', 'disabled' );
			$opts->optReset( 'enable_audit_trail' );
		}
		if ( $opts->optIs( 'enable_comments_filter', 'N' ) ) {
			$opts->optReset( 'enable_antibot_comments' );
			$opts->optReset( 'enable_comments_human_spam_filter' );
			$opts->optReset( 'trusted_user_roles' );
			$opts->optSet( 'comments_cooldown', 0 );
			$opts->optReset( 'enable_comments_filter' );
		}
		if ( $opts->optIs( 'enable_firewall', 'N' ) ) {
			$opts->optReset( 'block_send_email' );
			$opts->optSet( 'block_dir_traversal', 'N' );
			$opts->optSet( 'block_sql_queries', 'N' );
			$opts->optSet( 'block_field_truncation', 'N' );
			$opts->optSet( 'block_php_code', 'N' );
			$opts->optSet( 'block_aggressive', 'N' );
			$opts->optReset( 'enable_firewall' );
		}
		if ( $opts->optIs( 'enable_hack_protect', 'N' ) ) {
			$opts->optSet( 'section_file_guard', 'N' );
			$opts->optReset( 'file_locker' );
			$opts->optSet( 'enable_wpvuln_scan', 'N' );
			$opts->optSet( 'ptg_reinstall_links', 'N' );
			$opts->optReset( 'enable_hack_protect' );
		}
		if ( $opts->optIs( 'enable_headers', 'N' ) ) {
			$opts->optSet( 'x_frame', 'off' );
			$opts->optSet( 'x_xss_protect', 'N' );
			$opts->optSet( 'x_content_type', 'N' );
			$opts->optSet( 'x_referrer_policy', 'disabled' );
			$opts->optReset( 'enable_x_content_security_policy' );
			$opts->optReset( 'enable_headers' );
		}
		if ( $opts->optIs( 'enable_integrations', 'N' ) ) {
			$opts->optReset( 'enable_mainwp' );
			$opts->optReset( 'suresend_emails' );
			$opts->optReset( 'form_spam_providers' );
			$opts->optReset( 'user_form_providers' );
			$opts->optReset( 'enable_integrations' );
		}
		if ( $opts->optIs( 'enable_ips', 'N' ) ) {
			$opts->optSet( 'transgression_limit', 0 );
			$opts->optSet( 'cs_block', 'disabled' );
			$opts->optReset( 'track_fakewebcrawler' );
			$opts->optReset( 'track_logininvalid' );
			$opts->optReset( 'track_xmlrpc' );
			$opts->optReset( 'track_404' );
			$opts->optReset( 'track_linkcheese' );
			$opts->optReset( 'track_invalidscript' );
			$opts->optReset( 'track_useragent' );
			$opts->optSet( 'track_loginfailed', 'disabled' );
			$opts->optReset( 'enable_ips' );
		}
		if ( $opts->optIs( 'enable_lockdown', 'N' ) ) {
			$opts->optReset( 'disable_xmlrpc' );
			$opts->optReset( 'disable_anonymous_restapi' );
			$opts->optSet( 'disable_file_editing', 'N' );
			$opts->optSet( 'block_author_discovery', 'N' );
			$opts->optSet( 'clean_wp_rubbish', 'N' );
			$opts->optReset( 'enable_lockdown' );
		}
		if ( $opts->optIs( 'enable_login_protect', 'N' ) ) {
			$opts->optReset( 'enable_antibot_check' );
			$opts->optSet( 'login_limit_interval', 0 );
			$opts->optReset( 'enable_passkeys' );
			$opts->optReset( 'enable_email_authentication' );
			$opts->optReset( 'enable_google_authenticator' );
			$opts->optReset( 'enable_yubikey' );
			$opts->optReset( 'rename_wplogin_path' );
			$opts->optReset( 'enable_login_protect' );
		}
		if ( $opts->optIs( 'enable_traffic', 'N' ) ) {
			$opts->optReset( 'enable_logger' );
			$opts->optReset( 'enable_live_log' );
			$opts->optReset( 'enable_limiter' );
			$opts->optReset( 'enable_traffic' );
		}
		if ( $opts->optIs( 'enable_user_management', 'N' ) ) {
			$opts->optReset( 'enable_user_login_email_notification' );
			$opts->optReset( 'enable_admin_login_email_notification' );
			$opts->optSet( 'session_timeout_interval', 0 );
			$opts->optSet( 'session_idle_timeout_interval', 0 );
			$opts->optReset( 'session_lock' );
			$opts->optReset( 'reg_email_validate' );
			$opts->optReset( 'email_checks' );
			$opts->optReset( 'enable_password_policies' );
			$opts->optReset( 'auto_idle_days' );
			$opts->optReset( 'pass_expire' );
			$opts->optReset( 'enable_user_management' );
		}
	}
}
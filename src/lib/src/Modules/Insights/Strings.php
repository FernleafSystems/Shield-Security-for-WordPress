<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[]
	 */
	public function getInsightStatNames() :array {
		return [
			'key_success'                  => __( 'Successful authentication with Security Admin', 'wp-simple-firewall' ),
			'key_fail'                     => __( 'Failed authentication with Security Admin', 'wp-simple-firewall' ),
			'test_cron_run'                => __( 'Simple Test Cron', 'wp-simple-firewall' ),
			'apc_scan_run'                 => __( 'Scanned for abandoned plugins', 'wp-simple-firewall' ),
			'mal_scan_run'                 => __( 'Scanned for malware', 'wp-simple-firewall' ),
			'ptg_scan_run'                 => __( 'Scanned for altered plugin/theme files', 'wp-simple-firewall' ),
			'ufc_scan_run'                 => __( 'Scanned for unrecognised files', 'wp-simple-firewall' ),
			'wcf_scan_run'                 => __( 'Scanned Core files', 'wp-simple-firewall' ),
			'wpv_scan_run'                 => __( 'Scanned for vulnerabilities', 'wp-simple-firewall' ),
			'wcf_scan_found'               => __( 'Found modified core file', 'wp-simple-firewall' ),
			'apc_scan_found'               => __( 'Found abandoned plugin', 'wp-simple-firewall' ),
			'mal_scan_found'               => __( 'Found malware in file', 'wp-simple-firewall' ),
			'ptg_scan_found'               => __( 'Found altered plugin/themes file', 'wp-simple-firewall' ),
			'ufc_scan_found'               => __( 'Found unrecognised file', 'wp-simple-firewall' ),
			'wpv_scan_found'               => __( 'Found vulnerable item', 'wp-simple-firewall' ),
			'apc_item_repair_success'      => __( 'Repaired abandoned plugin', 'wp-simple-firewall' ),
			'mal_item_repair_success'      => __( 'Repaired file with malware', 'wp-simple-firewall' ),
			'ptg_item_repair_success'      => __( 'Repaired plugin/theme file', 'wp-simple-firewall' ),
			'ufc_item_repair_success'      => __( 'Repaired/Deleted unrecognised file', 'wp-simple-firewall' ),
			'wcf_item_repair_success'      => __( 'Repaired Core file', 'wp-simple-firewall' ),
			'wpv_item_repair_success'      => __( 'Repaired vulnerable item', 'wp-simple-firewall' ),
			'session_terminate'            => __( 'User session terminated and forced to re-login', 'wp-simple-firewall' ),
			'conn_kill'                    => __( 'Connection killed for blocked IP address', 'wp-simple-firewall' ),
			'ip_offense'                   => __( 'Offense registered against IP address', 'wp-simple-firewall' ),
			'ip_blocked'                   => __( 'IP address blocked after too many offenses', 'wp-simple-firewall' ),
			'spam_block_bot'               => __( 'Detected comment SPAM from bot', 'wp-simple-firewall' ),
			'spam_block_recaptcha'         => __( 'Detected comment SPAM from failed reCAPTCHA', 'wp-simple-firewall' ),
			'spam_block_human'             => __( 'Detected human comment SPAM with suspicious content', 'wp-simple-firewall' ),
			'2fa_success'                  => __( 'Successful 2-FA Login', 'wp-simple-firewall' ),
			'login_block'                  => __( 'Blocked Login', 'wp-simple-firewall' ),
			'password_policy_force_change' => __( 'Forced password update due to policy', 'wp-simple-firewall' ),
			'password_policy_block'        => __( 'Prevented password update due to policy', 'wp-simple-firewall' ),
			'firewall_block'               => __( 'Firewall Block', 'wp-simple-firewall' ),
			'options_exported'             => __( 'Options exported', 'wp-simple-firewall' ),
			'options_imported'             => __( 'Options imported', 'wp-simple-firewall' ),
			'block_anonymous_restapi'      => __( 'Blocked anonymous Rest API', 'wp-simple-firewall' ),
			'block_xml'                    => __( 'Blocked XML-RPC', 'wp-simple-firewall' ),
			'user_hard_suspended'          => __( 'User account suspended by administrator', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() :array {
		$sName = $this->getCon()->getHumanName();
		return [
			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $sName ),
			'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'box_welcome_title'   => sprintf( __( 'Welcome To %s Security Insights Dashboard', 'wp-simple-firewall' ), $sName ),
			'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), $sName ),
			'options'             => __( 'Options', 'wp-simple-firewall' ),
			'not_available'       => __( 'Sorry, this feature is included with Pro subscriptions.', 'wp-simple-firewall' ),
			'not_enabled'         => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade'      => __( 'You can get this feature (along with loads more) by going Pro.', 'wp-simple-firewall' ),
			'please_enable'       => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'no_security_notices' => __( 'There are no important security notices at this time.', 'wp-simple-firewall' ),
			'this_is_wonderful'   => __( 'This is wonderful!', 'wp-simple-firewall' ),
			'yyyymmdd'            => __( 'YYYY-MM-DD', 'wp-simple-firewall' ),
		];
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[]
	 */
	public function getInsightStatNames() {
		return [
			'insights_test_cron_last_run_at'        => __( 'Simple Test Cron', 'wp-simple-firewall' ),
			'insights_last_scan_ufc_at'             => __( 'Unrecognised Files Scan', 'wp-simple-firewall' ),
			'insights_last_scan_apc_at'             => __( 'Abandoned Plugins Scan', 'wp-simple-firewall' ),
			'insights_last_scan_wcf_at'             => __( 'WordPress Core Files Scan', 'wp-simple-firewall' ),
			'insights_last_scan_ptg_at'             => __( 'Plugin/Themes Guard Scan', 'wp-simple-firewall' ),
			'insights_last_scan_wpv_at'             => __( 'Vulnerabilities Scan', 'wp-simple-firewall' ),
			'insights_last_2fa_login_at'            => __( 'Successful 2-FA Login', 'wp-simple-firewall' ),
			'insights_last_login_block_at'          => __( 'Login Block', 'wp-simple-firewall' ),
			'insights_last_register_block_at'       => __( 'User Registration Block', 'wp-simple-firewall' ),
			'insights_last_reset-password_block_at' => __( 'Reset Password Block', 'wp-simple-firewall' ),
			'insights_last_firewall_block_at'       => __( 'Firewall Block', 'wp-simple-firewall' ),
			'insights_last_idle_logout_at'          => __( 'Idle Logout', 'wp-simple-firewall' ),
			'insights_last_password_block_at'       => __( 'Password Block', 'wp-simple-firewall' ),
			'insights_last_comment_block_at'        => __( 'Comment SPAM Block', 'wp-simple-firewall' ),
			'insights_xml_block_at'                 => __( 'XML-RPC Block', 'wp-simple-firewall' ),
			'insights_restapi_block_at'             => __( 'Anonymous Rest API Block', 'wp-simple-firewall' ),
			'insights_last_transgression_at'        => sprintf( __( '%s Offense', 'wp-simple-firewall' ), $this->getCon()
																											   ->getHumanName() ),
			'insights_last_ip_block_at'             => __( 'IP Connection Blocked', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		$sName = $this->getCon()->getHumanName();
		return [
			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $sName ),
			'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'box_welcome_title'   => sprintf( __( 'Welcome To %s Security Insights Dashboard', 'wp-simple-firewall' ), $sName ),
			'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), $sName ),

			'never'          => __( 'Never', 'wp-simple-firewall' ),
			'go_pro'         => 'Go Pro!',
			'options'        => __( 'Options', 'wp-simple-firewall' ),
			'not_available'  => __( 'Sorry, this feature would typically be used by professionals and so is a Pro-only feature.', 'wp-simple-firewall' ),
			'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade' => __( 'You can activate this feature (along with many others) and support development of this plugin for just $12.', 'wp-simple-firewall' ),
			'please_enable'  => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'only_1_dollar'  => __( 'for just $1/month', 'wp-simple-firewall' ),
		];
	}
}
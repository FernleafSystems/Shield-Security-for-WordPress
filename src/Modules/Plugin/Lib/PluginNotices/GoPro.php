<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Services\Services;

class GoPro extends Base {

	/**
	 * An offer, so it waits out a new install's first day. Its wording changes from day to day, not from page
	 * to page: the same line on every page of a visit reads as one notice, not as a new one each time.
	 */
	public function check() :?array {
		$con = self::con();
		$issue = null;
		if ( !$con->isPremiumActive() && !$this->isNewInstall() ) {
			$messages = [
				/* translators: %s: plugin name */
				sprintf( esc_html__( "Elevate your WordPress security to best-in-class, by upgrading your %s plan.", 'wp-simple-firewall' ), self::con()->labels->Name ),
				esc_html__( "Maximise your WordPress protection from bad bots, hackers, spammers, and malware.", 'wp-simple-firewall' ),
				/* translators: %s: plugin name */
				sprintf( esc_html__( "Discover the most comprehensive WordPress security when you upgrade your %s plan.", 'wp-simple-firewall' ), self::con()->labels->Name ),
				esc_html__( "Free security is great, but if you run a business, you'll want all the protection available.", 'wp-simple-firewall' ),
				esc_html__( "AI Malware Scanner, Auto-File Repair, WP Config protection, are just some of what's available when you upgrade your plan.", 'wp-simple-firewall' ),
				esc_html__( "Whitelabel, Manual/Auto User Suspension, Unlimited Logs, Site Sync Import/Export, are just some of what's available when you upgrade your plan.", 'wp-simple-firewall' ),
			];
			$issue = [
				'id'        => 'go_pro',
				'type'      => 'info',
				'text'      => [
					\implode( ' ', [
						sprintf( '%s %s',
							$messages[ \intdiv( Services::Request()->ts(), 86400 )%\count( $messages ) ],
							sprintf( '<a href="%s" class="btn btn-sm btn-light link-underline-opacity-25" style="--bs-btn-padding-y: 0;--bs-btn-padding-x: .35rem;" target="_blank">%s</a>',
								'https://clk.shldscrty.com/buyshieldpricing',
								esc_html__( 'View Plans', 'wp-simple-firewall' )
							)
						)
					] ),
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}

		return $issue;
	}
}

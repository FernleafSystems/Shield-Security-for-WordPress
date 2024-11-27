<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Services\Utilities\Arrays;

class GoPro extends Base {

	public function check() :?array {
		$con = self::con();
		$issue = null;
		if ( !$con->isPremiumActive() ) {
			$issue = [
				'id'        => 'go_pro',
				'type'      => 'info',
				'text'      => [
					\implode( ' ', [
						sprintf( '%s %s',
							\current( Arrays::RandomPluck( [
								__( "Elevate your WordPress security to best-in-class, by upgrading your Shield Security plan.", 'wp-simple-firewall' ),
								__( "Maximise your WordPress protection from bad bots, hackers, spammers, and malware.", 'wp-simple-firewall' ),
								__( "Discover the most comprehensive WordPress security when you upgrade your Shield Security plan.", 'wp-simple-firewall' ),
								__( "Free security is great, but if you run a business, you'll want all the protection available.", 'wp-simple-firewall' ),
								__( "AI Malware Scanner, Auto-File Repair, WP Config protection, are just some of what's available when you upgrade your plan.", 'wp-simple-firewall' ),
								__( "Whitelabel, Manual/Auto User Suspension, Unlimited Logs, Site Sync Import/Export, are just some of what's available when you upgrade your plan.", 'wp-simple-firewall' ),
							] ) ),
							sprintf( '<a href="%s" class="btn btn-sm btn-light link-underline-opacity-25" style="--bs-btn-padding-y: 0;--bs-btn-padding-x: .35rem;" target="_blank">%s</a>',
								'https://clk.shldscrty.com/buyshieldpricing',
								__( 'View Plans', 'wp-simple-firewall' )
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

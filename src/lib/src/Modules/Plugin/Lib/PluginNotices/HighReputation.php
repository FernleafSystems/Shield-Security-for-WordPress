<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\SilentCaptcha;

class HighReputation extends Base {

	public function check() :?array {
		$con = self::con();
		return ( $con->this_req->is_ip_high_reputation && $con->this_req->is_ip_blocked_shield_auto ) ?
			[
				'id'        => 'is_ip_high_reputation',
				'type'      => 'warning',
				'text'      => [
					sprintf(
						'%s %s',
						sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
							sprintf( __( "Your IP address is currently on the Auto-Block list, but you're not being block because your IP address is considered a High Reputation IP.", 'wp-simple-firewall' ), $con->labels->Name ) ),
						sprintf( '<a href="%s" class="">%s</a>',
							$con->plugin_urls->cfgForZoneComponent( SilentCaptcha::Slug() ),
							__( 'View IP Reputation Option', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
					'wp_admin',
				]
			]
			: null;
	}
}
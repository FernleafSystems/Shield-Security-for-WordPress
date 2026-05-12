<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

class SiteInLockdown extends Base {

	public function check() :?array {
		$con = self::con();
		return $con->this_req->is_site_lockdown_active ?
			[
				'id'        => 'site_in_lockdown',
				'type'      => 'danger',
				'text'      => [
					sprintf(
						'%s %s',
						sprintf( '%s: %s',
							__( 'Warning', 'wp-simple-firewall' ),
							sprintf( __( 'Your site is in lockdown.', 'wp-simple-firewall' ), $con->labels->Name )
						),
						sprintf( '<a href="%s">%s</a>',
							$con->plugin_urls->lockdown(),
							__( "Configure Lockdown", 'wp-simple-firewall' )
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

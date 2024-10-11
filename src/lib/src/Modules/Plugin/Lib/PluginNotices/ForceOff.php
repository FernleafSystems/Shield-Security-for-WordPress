<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginDeleteForceOff;

class ForceOff extends Base {

	public function check() :?array {
		$con = self::con();
		return $con->this_req->is_force_off ?
			[
				'id'        => 'force_off_active',
				'type'      => 'danger',
				'text'      => [
					sprintf(
						'%s %s',
						sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not currently protecting your site.', 'wp-simple-firewall' ), $con->labels->Name ) ),
						sprintf( '<a href="%s" data-notice_action="%s" class="shield_admin_notice_action">%s</a>',
							'#',
							PluginDeleteForceOff::SLUG,
							__( "Delete 'forceoff' File", 'wp-simple-firewall' )
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
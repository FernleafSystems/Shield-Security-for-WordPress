<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Services\Services;

class SelfUpdateAvailable extends Base {

	public function check() :?array {
		return Services::WpPlugins()->isUpdateAvailable( $this->con()->base_file ) ?
			[
				'id'        => 'self_update_available',
				'type'      => 'info',
				'text'      => [
					sprintf(
						'%s %s',
						__( "An upgrade is available for the Shield plugin.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="">%s</a>',
							Services::WpPlugins()->getUrl_Upgrade( $this->con()->base_file ),
							__( 'Upgrade Now', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
				]
			]
			: null;
	}
}
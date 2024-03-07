<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;

abstract class PageTrafficLogBase extends BasePluginAdminPage {

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		return [
			[
				'text'     => __( 'Download Traffic Logs', 'wp-simple-firewall' ),
				'href'     => $con->plugin_urls->fileDownloadAsStream( 'traffic' ),
				'disabled' => !$con->isPremiumActive(),
			],
			[
				'text'    => __( 'Configure Traffic Logging', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_form_mod_cfg' ],
				'datas'   => [
					'config_item' => EnumModules::TRAFFIC
				],
			],
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'text'       => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Traffic Log', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/264-review-your-site-traffic-with-the-traffic-log-viewer',
			'new_window' => true,
		];
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class PageTrafficLogBase extends BasePluginAdminPage {

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		return [
			[
				'title'    => __( 'Download Request Logs', 'wp-simple-firewall' ),
				'href'     => $con->plugin_urls->fileDownloadAsStream( 'traffic' ),
				'disabled' => !$con->isPremiumActive(),
			],
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Request Log', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/264-review-your-site-traffic-with-the-traffic-log-viewer',
			'new_window' => true,
		];
	}
}
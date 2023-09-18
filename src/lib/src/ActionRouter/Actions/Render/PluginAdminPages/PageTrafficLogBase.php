<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

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
				'text' => __( 'Configure Traffic Logging', 'wp-simple-firewall' ),
				'href' => $con->plugin_urls->offCanvasConfigRender( $con->getModule_Traffic()->cfg->slug ),
			],
		];
	}
}
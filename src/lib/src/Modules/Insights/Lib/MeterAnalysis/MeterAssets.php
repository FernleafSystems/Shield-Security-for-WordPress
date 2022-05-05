<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterAssets extends MeterBase {

	const SLUG = 'assets';

	protected function title() :string {
		return __( 'Plugins, Themes, WordPress Core', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How well core WordPress assets are protected', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "This section reviews how your plugins & themes are scanned, where there are unused items, and any particular issues that need to be addressed.", 'wp-simple-firewall' ),
			__( "Generally you should keep all assets updated, remove unused items, and use only plugins that are regularly maintained.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		return [
			'autoupdate_core',
			'wpv_scanner',
			'vuln_autoupdate',
			'apc_scanner',
			'plugins_updates',
			'themes_updates',
			'plugins_inactive',
			'themes_inactive',
		];
	}
}
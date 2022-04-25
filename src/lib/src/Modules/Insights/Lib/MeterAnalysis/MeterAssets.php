<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterAssets extends MeterBase {

	const SLUG = 'assets';

	protected function title() :string {
		return __( 'Plugins, Themes, WordPress Core', 'wp-simple-firewall' );
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
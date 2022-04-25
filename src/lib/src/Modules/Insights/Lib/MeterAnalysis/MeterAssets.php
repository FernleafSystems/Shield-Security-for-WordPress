<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterAssets extends MeterBase {

	const SLUG = 'assets';

	protected function title() :string {
		return __( 'Plugins, Themes, WordPress Core', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'plugins_inactive',
			'plugins_updates',
			'themes_inactive',
			'themes_updates',
			'apc_scanner',
		];
	}
}
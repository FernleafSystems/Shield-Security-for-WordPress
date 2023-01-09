<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterAssets extends MeterBase {

	public const SLUG = 'assets';

	public function title() :string {
		return __( 'Plugins, Themes, WordPress Core', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How well core WordPress assets are protected', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "This section reviews how your plugins & themes are scanned, where there are unused items, and any particular issues that need to be addressed.", 'wp-simple-firewall' ),
			__( "Generally you should keep all assets updated, remove unused items, and use only plugins that are regularly maintained.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\WpCoreAutoUpdate::class,
			Component\ScanEnabledWpv::class,
			Component\ScanEnabledWpvAutoupdate::class,
			Component\ScanEnabledApc::class,
			Component\WpPluginsUpdates::class,
			Component\WpThemesUpdates::class,
			Component\WpPluginsInactive::class,
			Component\WpThemesInactive::class,
		];
	}
}
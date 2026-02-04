<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterAssets extends MeterBase {

	public const SLUG = 'assets';

	public function title() :string {
		return __( 'Plugins, Themes, WordPress Core', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How well core WordPress plugins and themes are protected', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "Your #1 risk of security vulnerabilities comes from WordPress Plugins (not WordPress itself).", 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "Good plugin and theme hygiene is crucial to keeping your WordPress site healthy and free of vulnerabilities.", 'wp-simple-firewall' ),
				__( "To stay healthy involves actively scanning files for unexpected changes and detection of files that don't belong.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "You should keep all plugins and themes up-to-date, and remove anything you aren't using.", 'wp-simple-firewall' ),
				__( "Plugins are easily installed, so if you ever remove something you need to use again at a later date, reinstalling it is easy.", 'wp-simple-firewall' ),
			] ),
		];
	}

	protected function getComponents() :array {
		return [
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
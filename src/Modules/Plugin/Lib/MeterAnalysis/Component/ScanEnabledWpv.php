<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledWpv extends ScanEnabledBase {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'scan_enabled_wpv';
	public const WEIGHT = 5;

	protected function getOptConfigKey() :string {
		return 'enable_wpvuln_scan';
	}

	public function title() :string {
		return __( 'Vulnerable Plugins & Themes', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Plugins and Themes are scanned for known vulnerabilities.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Plugins and Themes aren't scanned for known vulnerabilities.", 'wp-simple-firewall' );
	}
}
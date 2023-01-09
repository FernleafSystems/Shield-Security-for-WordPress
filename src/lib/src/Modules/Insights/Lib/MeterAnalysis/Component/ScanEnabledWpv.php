<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class ScanEnabledWpv extends ScanEnabledBase {

	public const SLUG = 'scan_enabled_wpv';

	public function title() :string {
		return __( 'Vulnerable Plugins & Themes', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'enable_wpvuln_scan' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Plugins and Themes are scanned for known vulnerabilities.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Plugins and Themes aren't scanned for known vulnerabilities.", 'wp-simple-firewall' );
	}
}
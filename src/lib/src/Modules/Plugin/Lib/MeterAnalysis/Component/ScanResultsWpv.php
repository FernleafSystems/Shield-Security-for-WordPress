<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsWpv extends ScanResultsBase {

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'scan_results_wpv';

	protected function countResults() :int {
		return self::con()->comps->scans->getScanResultsCount()->countVulnerableAssets();
	}

	public function title() :string {
		return __( 'Vulnerable Plugins & Themes', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "There doesn't appear to be any plugins or themes with known vulnerabilities.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There appears to be at least 1 vulnerable plugin or theme installed on your site.", 'wp-simple-firewall' );
	}
}
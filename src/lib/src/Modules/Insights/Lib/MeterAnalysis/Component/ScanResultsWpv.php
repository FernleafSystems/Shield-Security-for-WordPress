<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class ScanResultsWpv extends ScanResultsBase {

	public const SLUG = 'scan_results_wpv';

	protected function countResults() :int {
		return $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount()->countVulnerableAssets();
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
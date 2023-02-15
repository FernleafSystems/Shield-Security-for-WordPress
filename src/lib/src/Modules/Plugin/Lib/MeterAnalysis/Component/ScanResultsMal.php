<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsMal extends ScanResultsBase {

	public const SLUG = 'scan_results_mal';

	protected function countResults() :int {
		return $this->getCon()->getModule_HackGuard()->getScansCon()->getScanResultsCount()->countMalware();
	}

	public function title() :string {
		return $this->isProtected() ? __( 'No Potential Malware Found', 'wp-simple-firewall' ) : __( 'Potential Malware Found', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "There doesn't appear to be any PHP malware files on your site.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There appears to be at least 1 PHP malware file on your site.", 'wp-simple-firewall' );
	}
}
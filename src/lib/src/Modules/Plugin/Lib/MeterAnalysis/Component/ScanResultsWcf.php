<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsWcf extends ScanResultsBase {

	public const SLUG = 'scan_results_wcf';

	protected function countResults() :int {
		return self::con()->comps->scans->getScanResultsCount()->countWPFiles();
	}

	public function title() :string {
		return __( 'WordPress Core Files', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "All WordPress Core files appear to be clean and unmodified.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "At least 1 WordPress Core file appears to be modified or unrecognised.", 'wp-simple-firewall' );
	}
}
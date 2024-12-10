<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsPtg extends ScanResultsBase {

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'scan_results_ptg';

	protected function countResults() :int {
		$counter = self::con()->comps->scans->getScanResultsCount();
		return $counter->countThemeFiles() + $counter->countPluginFiles();
	}

	public function title() :string {
		return __( 'Plugin & Theme Files', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All plugin & theme files appear to be valid.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( 'At least 1 of your plugins or themes appears to be modified.', 'wp-simple-firewall' );
	}
}
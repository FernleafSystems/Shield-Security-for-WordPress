<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsThemeFiles extends ScanResultsBase {

	public const SLUG = 'scan_results_theme_files';

	protected function countResults() :int {
		return self::con()->comps->scans->getScanResultsCount()->countThemeFiles();
	}

	public function title() :string {
		return __( 'Theme Files', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All theme files appear to be valid.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( 'At least 1 theme file appears to be modified.', 'wp-simple-firewall' );
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsPluginFiles extends ScanResultsBase {

	public const SLUG = 'scan_results_plugin_files';

	protected function countResults() :int {
		return self::con()->comps->scans->getScanResultsCount()->countPluginFiles();
	}

	public function title() :string {
		return __( 'Plugin Files', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All plugin files appear to be valid.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( 'At least 1 plugin file appears to be modified.', 'wp-simple-firewall' );
	}
}

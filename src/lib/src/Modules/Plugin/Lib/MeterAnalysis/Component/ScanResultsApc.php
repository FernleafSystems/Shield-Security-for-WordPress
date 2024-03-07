<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanResultsApc extends ScanResultsBase {

	public const SLUG = 'scan_results_apc';
	public const WEIGHT = 4;

	protected function countResults() :int {
		return self::con()->comps->scans->getScanResultsCount()->countAbandoned();
	}

	public function title() :string {
		return __( 'Abandoned Plugins Found', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "There doesn't appear to be any abandoned plugins on your site.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( "There appears to be at least %s abandoned plugin(s) installed on your site.", 'wp-simple-firewall' ),
			$this->countResults() );
	}
}
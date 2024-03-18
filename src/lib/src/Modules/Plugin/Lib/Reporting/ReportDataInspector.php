<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ReportDataInspector {

	use PluginControllerConsumer;

	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function countAll() :int {
		return $this->countScanResultsNew() + $this->countScanResultsCurrent()
			   + $this->countStatZonesWithNonZeroStats() + $this->countChangeZonesWithChanges();
	}

	public function countScanResultsCurrent() :int {
		return $this->countScanResults( 'scan_results_current' );
	}

	public function countScanResultsNew() :int {
		return $this->countScanResults( 'scan_results_new' );
	}

	public function countStatZonesWithNonZeroStats() :int {
		$total = 0;
		foreach ( $this->data[ Constants::REPORT_AREA_STATS ] ?? [] as $statZone ) {
			if ( $statZone[ 'has_non_zero_stat' ] ) {
				$total++;
			}
		}
		return $total;
	}

	public function countChangeZonesWithChanges() :int {
		$total = 0;
		foreach ( $this->data[ Constants::REPORT_AREA_CHANGES ] ?? [] as $changeZone ) {
			if ( $changeZone[ 'total' ] > 0 ) {
				$total++;
			}
		}
		return $total;
	}

	private function countScanResults( string $type ) :int {
		$total = 0;
		foreach ( $this->data[ Constants::REPORT_AREA_SCANS ][ $type ] ?? [] as $scanResultData ) {
			$total += $scanResultData[ 'count' ];
		}
		return $total;
	}
}
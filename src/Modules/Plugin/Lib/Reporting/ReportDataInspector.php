<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ReportDataInspector {

	use PluginControllerConsumer;

	private array $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function countAll() :int {
		return $this->countScanResultsNew() + $this->countScanResultsCurrent()
			   + $this->countStatZonesWithNonZeroStats() + $this->countChangeZonesWithChanges();
	}

	public function countScanResultsCurrent() :int {
		return $this->countScanResults( 'count' );
	}

	public function countScanResultsNew() :int {
		return $this->countScanResults( 'new_count' );
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

	private function countScanResults( string $field ) :int {
		$total = 0;
		foreach ( $this->data[ Constants::REPORT_AREA_SCANS ][ 'scan_results' ] ?? [] as $scanResultData ) {
			$total += $scanResultData[ $field ] ?? 0;
		}
		return $total;
	}
}
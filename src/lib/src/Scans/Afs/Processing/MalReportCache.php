<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MalReportCache {

	use ModConsumer;

	private $workingReportHash;

	public function setReportHash( string $reportHash ) :MalReportCache {
		$this->workingReportHash = $reportHash;
		return $this;
	}

	public function updateWithReport() {
		if ( !$this->isReported() ) {

			$this->getOptions()->setOpt( 'mal_fp_reports', array_filter(
				array_merge( $this->getMalFalsePositiveReports(), [
					$this->workingReportHash => Services::Request()->ts()
				] ),
				function ( $ts ) {
					return $ts > Services::Request()->carbon()->subMonth()->timestamp;
				}
			) );

			$this->getMod()->saveModOptions(); // important to save immediately due to async nature
		}
	}

	public function reportedAt() :int {
		return $this->getMalFalsePositiveReports()[ $this->workingReportHash ] ?? 0;
	}

	public function isReported() :bool {
		return $this->reportedAt() > 0;
	}

	/**
	 * @return int[] - keys are the unique report hash
	 */
	private function getMalFalsePositiveReports() :array {
		$FP = $this->getOptions()->getOpt( 'mal_fp_reports', [] );
		return is_array( $FP ) ? $FP : [];
	}
}
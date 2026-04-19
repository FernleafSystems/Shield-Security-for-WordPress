<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportsDB;

class AutoReportCoordinator {

	public function run() :void {
		$this->attemptAlert();
		$this->attempt( Constants::REPORT_TYPE_INFO );
	}

	protected function attemptAlert() :?ReportVO {
		try {
			$report = $this->createReport( Constants::REPORT_TYPE_ALERT );
			$report->record = $this->buildAndStoreReport( $report );
			if ( $this->persistAlertNotifications( $report ) ) {
				$this->sendNotificationEmail( $report );
			}
			return $report;
		}
		catch ( Exceptions\ReportBuildException $e ) {
			return null;
		}
	}

	protected function attempt( string $reportType ) :?ReportVO {
		try {
			$report = $this->createReport( $reportType );
			$report->record = $this->buildAndStoreReport( $report );
			$this->sendNotificationEmail( $report );
			return $report;
		}
		catch ( Exceptions\ReportBuildException $e ) {
			return null;
		}
	}

	protected function createReport( string $reportType ) :ReportVO {
		return ( new CreateReportVO() )->create( $reportType );
	}

	protected function buildAndStoreReport( ReportVO $report ) :ReportsDB\Record {
		return ( new ReportGenerator() )->buildAndStore( $report );
	}

	protected function sendNotificationEmail( ReportVO $report ) :void {
		( new ReportGenerator() )->sendNotificationEmail( $report );
	}

	protected function persistAlertNotifications( ReportVO $report ) :bool {
		return ( new ReportGenerator() )->persistAlertNotifications( $report );
	}
}

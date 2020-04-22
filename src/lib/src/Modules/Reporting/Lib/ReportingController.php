<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController {

	use Modules\ModConsumer;
	use Modules\Base\OneTimeExecute;

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		$this->buildAndSendReport();
	}

	private function buildAndSendReport() {
		$aReports = [];

		try {
			$oAlertReport = $this->buildReportAlerts();
			if ( !empty( $oAlertReport->content ) ) {
				$this->storeReportRecord( $oAlertReport );
				$aReports[] = $oAlertReport;
			}
		}
		catch ( \Exception $oE ) {
		}

		try {
			$oInfoReport = $this->buildReportInfo();
			if ( !empty( $oInfoReport->content ) ) {
				$this->storeReportRecord( $oInfoReport );
				$aReports[] = $oInfoReport;
			}
		}
		catch ( \Exception $oE ) {
		}

		$this->sendEmail( $aReports );
	}

	/**
	 * @param Reports\ReportVO $oReport
	 * @return bool
	 */
	private function storeReportRecord( Reports\ReportVO $oReport ) {
		$oRecord = new DBReports\EntryVO();
		$oRecord->sent_at = Services::Request()->ts();
		$oRecord->rid = $oReport->rid;
		$oRecord->type = $oReport->type;
		$oRecord->frequency = $oReport->interval;
		$oRecord->interval_end_at = $oReport->interval_end_at;

		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		return $oMod->getDbHandler_Reports()
					->getQueryInserter()
					->insert( $oRecord );
	}

	/**
	 * @return Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportAlerts() {
		$oReport = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_ALERT ) )
			->setMod( $this->getMod() )
			->create();
		( new Reports\BuildAlerts( $oReport ) )
			->setMod( $this->getMod() )
			->build();
		return $oReport;
	}

	/**
	 * @return Reports\ReportVO
	 * @throws \Exception
	 */
	private function buildReportInfo() {
		$oReport = ( new Reports\CreateReportVO( DBReports\Handler::TYPE_INFO ) )
			->setMod( $this->getMod() )
			->create();
		( new Reports\BuildInfo( $oReport ) )
			->setMod( $this->getMod() )
			->build();
		return $oReport;
	}

	/**
	 * @param Reports\ReportVO[] $aReportVOs
	 */
	private function sendEmail( array $aReportVOs ) {

		$aReports = array_filter( array_map(
			function ( $oReport ) {
				return $oReport->content;
			},
			$aReportVOs
		) );

		if ( !empty( $aReports ) ) {
			$oWP = Services::WpGeneral();
			$aReports = array_merge(
				[
					__( 'Please find your site report below.', 'wp-simple-firewall' ),
					__( 'Depending on your settings and cron timings, this report may contain a combination of alerts, statistics and other information.', 'wp-simple-firewall' ),
					'',
					sprintf( '- %s: %s', __( 'Site URL', 'wp-simple-firewall' ), $oWP->getHomeUrl() ),
					sprintf( '- %s: %s', __( 'Report Generation Date', 'wp-simple-firewall' ),
						$oWP->getTimeStampForDisplay() ),
					'',
					__( 'Please use the links provided to review the report details.', 'wp-simple-firewall' ),
				],
				$aReports,
				[
					__( 'Thank You.', 'wp-simple-firewall' ),
				]
			);
			$this->getMod()
				 ->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getMod()->getPluginReportEmail(),
					 __( 'Site Report', 'wp-simple-firewall' ).' - '.$this->getCon()->getHumanName(),
					 $aReports
				 );
		}
	}
}
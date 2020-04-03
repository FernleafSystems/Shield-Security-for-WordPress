<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController extends Base\OneTimeExecute {

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		try {
			$this->buildAndSendReport();
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	private function buildAndSendReport() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Reports();

		$oReportRecord = new DBReports\EntryVO();
		$oReportRecord->sent_at = Services::Request()->ts();

		$oAlertReport = $this->buildReportAlerts();
		if ( !empty( $oAlertReport->content ) ) {
			$oReportRecord->rid = $oAlertReport->rid;
			$oReportRecord->type = $oAlertReport->type;
			$oReportRecord->frequency = $oAlertReport->interval;
			$oReportRecord->interval_end_at = $oAlertReport->interval_end_at;
			$oDbH->getQueryInserter()->insert( $oReportRecord );
		}

		$oInfoReport = $this->buildReportInfo();
		if ( !empty( $oInfoReport->content ) ) {
			$oReportRecord->rid = $oInfoReport->rid;
			$oReportRecord->type = $oInfoReport->type;
			$oReportRecord->frequency = $oInfoReport->interval;
			$oReportRecord->interval_end_at = $oInfoReport->interval_end_at;
			$oDbH->getQueryInserter()->insert( $oReportRecord );
		}

		$this->sendEmail( [ $oAlertReport->content, $oInfoReport->content ] );
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
	 * @param array $aBody
	 */
	private function sendEmail( array $aBody ) {
		$aBody = array_filter( $aBody );
		if ( !empty( $aBody ) ) {
			$this->getMod()
				 ->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getMod()->getPluginDefaultRecipientAddress(),
					 'Shield Alert',
					 $aBody
				 );
		}
	}
}
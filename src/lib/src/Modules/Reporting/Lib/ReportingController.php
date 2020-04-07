<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as DBReports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;
use FernleafSystems\Wordpress\Services\Services;

class ReportingController extends Base\OneTimeExecute {

	protected function run() {
		if (isset($_GET['test123'])) {
			try {
				$oAlertReport = $this->buildReportAlerts();
				var_dump($oAlertReport->content);
				$oInfoReport = $this->buildReportInfo();
				var_dump($oInfoReport->content);
			}
			catch ( \Exception $oE ) {
			}
			die();
		}
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
		$oWP = Services::WpGeneral();
		$aBody = array_filter( $aBody );
		if ( !empty( $aBody ) ) {
			$aBody = array_merge(
				[
					__( 'Please find your site report below.', 'wp-simple-firewall' ),
					__( 'Depending on your reporting settings and cron timings, this report may contain a mix of alerts, statistics and other information.', 'wp-simple-firewall' ),
					'',
					sprintf( '- %s: %s', __( 'Site URL', 'wp-simple-firewall' ), $oWP->getHomeUrl() ),
					sprintf( '- %s: %s', __( 'Report Generation Date', 'wp-simple-firewall' ),
						$oWP->getTimeStampForDisplay() ),
					'',
					__( 'Please use the links provided to review the report details.', 'wp-simple-firewall' ),
				],
				$aBody,
				[
					__( 'Thank You.', 'wp-simple-firewall' ),
				]
			);
			$this->getMod()
				 ->getEmailProcessor()
				 ->sendEmailWithWrap(
					 $this->getMod()->getPluginReportEmail(),
					 __( 'Site Report' ).' - '.$this->getCon()->getHumanName(),
					 $aBody
				 );
		}
	}
}
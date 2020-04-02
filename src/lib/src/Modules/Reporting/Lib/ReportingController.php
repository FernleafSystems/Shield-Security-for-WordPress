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
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	private function buildAndSendReport() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Reports();

		$oAlertReport = ( new Reports\CreateReportVO( $oDbH::TYPE_ALERT ) )
			->setMod( $oMod )
			->create();
		$oInfoReport = ( new Reports\CreateReportVO( $oDbH::TYPE_INFO ) )
			->setMod( $oMod )
			->create();

		( new Reports\BuildAlerts( $oAlertReport ) )
			->setMod( $oMod )
			->build();
		( new Reports\BuildInfo( $oInfoReport ) )
			->setMod( $oMod )
			->build();

		if ( !empty( $oInfoReport->content ) || !empty( $oAlertReport->content ) ) {
			$oReport = new DBReports\EntryVO();
			$oReport->sent_at = Services::Request()->ts();
			if ( !empty( $oAlertReport->content ) ) {
				$oReport->rid = $oAlertReport->rid;
				$oReport->type = $oAlertReport->type;
				$oReport->interval = $oAlertReport->interval;
				$oReport->interval_end_at = $oAlertReport->interval_end_at;
				$oDbH->getQueryInserter()->insert( $oReport );
			}
			if ( !empty( $oInfoReport->content ) ) {
				$oReport->rid = $oInfoReport->rid;
				$oReport->type = $oInfoReport->type;
				$oReport->interval = $oInfoReport->interval;
				$oReport->interval_end_at = $oInfoReport->interval_end_at;
				$oDbH->getQueryInserter()->insert( $oReport );
			}

			$this->sendEmail( [ $oAlertReport->content, $oInfoReport->content ] );
		}
	}

	/**
	 * @param array $aBody
	 */
	private function sendEmail( array $aBody ) {
		if ( !empty( $sBody ) ) {
			$this->getMod()
				 ->getEmailProcessor()
				 ->send(
					 $this->getMod()->getPluginDefaultRecipientAddress(),
					 'Shield Alert',
					 implode( "\n", array_filter( $aBody ) )
				 );
		}
	}
}
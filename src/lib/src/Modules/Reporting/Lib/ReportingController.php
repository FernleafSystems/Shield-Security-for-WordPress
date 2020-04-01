<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

class ReportingController extends Base\OneTimeExecute {

	protected function run() {
		if ( isset( $_GET[ 'test123' ] ) ) {
			$this->buildAndSendReport();
		}
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
		$this->buildAndSendReport();
	}

	private function buildAndSendReport() {
		$sBody = '';
		try {
			$sBody .= ( new Reports\BuildAlerts() )
				->setMod( $this->getMod() )
				->build();
			$bReportAlert = true;
		}
		catch ( \Exception $oE ) {
			$bReportAlert = false;
		}

		try {
			$sBody .= ( new Reports\BuildInfo() )
				->setMod( $this->getMod() )
				->build();
			$bReportInfo = true;
		}
		catch ( \Exception $oE ) {
			$bReportInfo = false;
		}

		if ( $bReportInfo || $bReportAlert ) {
			/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
			$oMod = $this->getMod();
			$oDbH = $oMod->getDbHandler_Reports();
			/** @var Databases\Reports\Select $oSel */
			$oSel = $oDbH->getQuerySelector();

			$nPrevReportId = $oSel->getLastReportId();
			$nNextReportId = is_numeric( $nPrevReportId ) ? $nPrevReportId + 1 : 1;

			/** @var Databases\Reports\Insert $oInserter */
			$oInserter = $oDbH->getQueryInserter();
			if ( $bReportAlert ) {
				$oInserter->create( $nNextReportId, $oDbH::TYPE_ALERT );
			}
			if ( $bReportInfo ) {
				$oInserter->create( $nNextReportId, $oDbH::TYPE_INFO );
			}
			$this->sendEmail( $sBody );
		}
	}

	/**
	 * @param string $sBody
	 */
	private function sendEmail( $sBody ) {
		if ( !empty( $sBody ) ) {
			$this->getMod()
				 ->getEmailProcessor()
				 ->send(
					 $this->getMod()->getPluginDefaultRecipientAddress(),
					 'Shield Alert',
					 $sBody
				 );
		}
	}

	public function purge() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		$oMod->getDbHandler_Reports()->deleteTable();
	}
}
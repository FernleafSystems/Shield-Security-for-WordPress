<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuild {

	use ModConsumer;

	/**
	 * @return string
	 */
	protected function getReportType() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Reports();
		return $oDbH::TYPE_ALERT;
	}

	/**
	 * @return Databases\Reports\EntryVO|null
	 */
	protected function getLastReport() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Reports\Select $oSel */
		$oSel = $oMod->getDbHandler_Reports()->getQuerySelector();
		/** @var Databases\Reports\EntryVO $oLast */
		return $oSel->filterByType( $this->getReportType() )
					->setOrderBy( 'sent_at', 'DESC' )
					->first();
	}

	/**
	 * @return int
	 * @throws \Exception
	 */
	protected function getBoundaryTs() {
		return 0;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToSend() {
		$oLast = $this->getLastReport();
		return empty( $oLast ) || $this->getBoundaryTs() > $oLast->sent_at;
	}

	/**
	 * @throws \Exception
	 */
	public function build() {
		// Are we ready to send alerts?
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		/** @var Reporting\Options $oOpts */
		$oOpts = $this->getOptions();
		$oDbH = $oMod->getDbHandler_Reports();
		/** @var Databases\Reports\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		/** @var Databases\Reports\EntryVO $oLast */
		$oLast = $oSel->filterByType( $oDbH::TYPE_ALERT )
					  ->setOrderBy( 'sent_at', 'DESC' )
					  ->first();

		$oBoundary = Services::Request()->carbon();
		switch ( $oOpts->getFrequencyAlerts() ) {
			case 'realtime':
				break;
			case 'hourly':
				$oBoundary->subHours( 1 );
				break;
			case 'daily':
				$oBoundary->subDays( 1 );
				break;
			case 'weekly':
				$oBoundary->subWeek( 1 );
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
				break;
		}
		if ( $oBoundary->timestamp < $oLast->sent_at ) {
			throw new \Exception( 'Not enough time has passed since previous report' );
		}

		// Do we have alerts?
		$aAlerts = $this->gatherAlerts();
		if ( empty( $aAlerts ) ) {
			throw new \Exception( 'no alerts to build' );
		}

		return $this->getMod()->renderTemplate(
			'/components/reports/alert_body.twig',
			[
				'vars'    => [
					'alerts' => $aAlerts
				],
				'strings' => [
					'title'    => __( 'Important Alerts', 'wp-simple-firewall' ),
					'subtitle' => __( 'The following is a collection of the latest alerts since your previous report.', 'wp-simple-firewall' ),
				],
			]
		);
	}

	/**
	 * @return string[]
	 */
	protected function gatherAlerts() {
		$aAlerts = [];
		foreach ( $this->getCon()->modules as $oMod ) {
			$oRepCon = $oMod->getReportingHandler();
			if ( $oRepCon instanceof BaseReporting ) {
				$aAlerts = array_merge(
					$aAlerts,
					$oRepCon->buildAlerts()
				);
			}
		}
		return $aAlerts;
	}
}
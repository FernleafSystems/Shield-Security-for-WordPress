<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class BuildAlerts extends BaseBuild {

	/**
	 * @throws \Exception
	 */
	public function build() {
		if ( !$this->isReadyToSend() ) {
			throw new \Exception( 'Not enough time has passed since previous report' );
		}

		$aAlerts = $this->gatherAlerts();
		if ( empty( $aAlerts ) ) {
			throw new \Exception( 'No alerts to build' );
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
	 * @return int
	 * @throws \Exception
	 */
	protected function getBoundaryTs() {
		// Are we ready to send alerts?
		/** @var Reporting\Options $oOpts */
		$oOpts = $this->getOptions();

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
		return $oBoundary->timestamp;
	}
}
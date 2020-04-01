<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class BuildInfo extends BaseBuild {

	/**
	 * @throws \Exception
	 */
	public function build() {
		if ( !$this->isReadyToSend() ) {
			throw new \Exception( 'Not enough time has passed since previous report' );
		}

		$aInfo = $this->gatherInfo();
		if ( empty( $aInfo ) ) {
			throw new \Exception( 'no info to build' );
		}

		return $this->getMod()->renderTemplate(
			'/components/reports/info_body.twig',
			[
				'vars'    => [
					'alerts' => $aInfo
				],
				'strings' => [
					'title'    => __( 'Site Information Update', 'wp-simple-firewall' ),
					'subtitle' => __( 'The following is a collection of the latest information since your previous report.', 'wp-simple-firewall' ),
				],
			]
		);
	}

	/**
	 * @return string[]
	 */
	protected function gatherInfo() {
		$aData = [];
		try {
			foreach ( $this->getCon()->modules as $oMod ) {
				$oRepCon = $oMod->getReportingHandler();
				if ( $oRepCon instanceof BaseReporting ) {
					$aData = array_merge( $aData, $oRepCon->buildInfo() );
				}
			}
		}
		catch ( \Exception $oE ) {
		}
		return $aData;
	}

	/**
	 * @return string
	 */
	protected function getReportType() {
		/** @var \ICWP_WPSF_FeatureHandler_Reporting $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Reports();
		return $oDbH::TYPE_INFO;
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
			case 'hourly':
				$oBoundary->subHours( 1 );
				break;
			case 'daily':
				$oBoundary->subDays( 1 );
				break;
			case 'weekly':
				$oBoundary->subWeeks( 1 );
				break;
			case 'biweekly':
				$oBoundary->subWeeks( 2 );
				break;
			case 'monthly':
				$oBoundary->subMonth( 1 );
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
				break;
		}
		return $oBoundary->timestamp;
	}
}
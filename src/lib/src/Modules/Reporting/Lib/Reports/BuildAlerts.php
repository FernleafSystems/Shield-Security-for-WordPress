<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;

class BuildAlerts extends BaseBuild {

	/**
	 * @return string[]
	 */
	protected function gather() {
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
	 * @param array $aGatheredData
	 * @return string
	 */
	protected function render( array $aGatheredData ) {
		return $this->getMod()->renderTemplate(
			'/components/reports/alert_body.twig',
			[
				'vars'    => [
					'alerts' => $aGatheredData
				],
				'strings' => [
					'title'    => __( 'Important Alerts', 'wp-simple-firewall' ),
					'subtitle' => __( 'The following is a collection of the latest alerts since your previous report.', 'wp-simple-firewall' ),
				],
			]
		);
	}
}
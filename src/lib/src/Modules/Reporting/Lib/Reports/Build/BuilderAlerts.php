<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;

class BuilderAlerts extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$aReports = [];
		foreach ( $this->getCon()->modules as $oMod ) {
			$oRepCon = $oMod->getReportingHandler();
			if ( $oRepCon instanceof BaseReporting ) {
				foreach ( $oRepCon->getAlertReporters() as $oReporter ) {
					$aReports = array_merge(
						$aReports,
						$oReporter->setReport( $this->rep )
								  ->build()
					);
				}
			}
		}
		return $aReports;
	}

	protected function render( array $aGatheredData ) :string {
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
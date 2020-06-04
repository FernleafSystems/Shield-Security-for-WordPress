<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;

class BuilderInfo extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() {
		$aReports = [];
		foreach ( $this->getCon()->modules as $oMod ) {
			$oRepCon = $oMod->getReportingHandler();
			if ( $oRepCon instanceof BaseReporting ) {
				foreach ( $oRepCon->getInfoReporters() as $oReporter ) {
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

	/**
	 * @inheritDoc
	 */
	protected function render( array $aGatheredData ) {
		return $this->getMod()->renderTemplate(
			'/components/reports/info_body.twig',
			[
				'vars'    => [
					'alerts' => $aGatheredData
				],
				'strings' => [
					'title'            => __( 'Site Information Report', 'wp-simple-firewall' ),
					'subtitle'         => __( 'The following is a collection of the latest information based on your reporting settings.', 'wp-simple-firewall' ),
					'dates_below'      => __( 'Information is for the following time period.', 'wp-simple-firewall' ),
					'reporting_period' => __( 'Reporting Period', 'wp-simple-firewall' ),
					'time_interval'    => $this->getTimeIntervalForDisplay(),
				],
			]
		);
	}
}
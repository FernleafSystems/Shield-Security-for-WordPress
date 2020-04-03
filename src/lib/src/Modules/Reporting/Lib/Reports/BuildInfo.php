<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;

class BuildInfo extends BaseBuild {

	/**
	 * @inheritDoc
	 */
	protected function gather() {
		$aData = [];
		try {
			foreach ( $this->getCon()->modules as $oMod ) {
				$oRepCon = $oMod->getReportingHandler();
				if ( $oRepCon instanceof BaseReporting ) {
					$aData = array_merge( $aData, $oRepCon->buildInfo( $this->rep ) );
				}
			}
		}
		catch ( \Exception $oE ) {
		}
		return $aData;
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
					'reporting_period'      => __( 'Reporting Period', 'wp-simple-firewall' ),
					'time_interval'    => $this->getTimeIntervalForDisplay(),
				],
			]
		);
	}
}
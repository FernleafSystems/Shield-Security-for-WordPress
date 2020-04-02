<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Services\Services;

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
		$oWP = Services::WpGeneral();
		return $this->getMod()->renderTemplate(
			'/components/reports/info_body.twig',
			[
				'vars'    => [
					'alerts' => $aGatheredData
				],
				'strings' => [
					'title'       => __( 'Site Information Update', 'wp-simple-firewall' ),
					'subtitle'    => __( 'The following is a collection of the latest information since your previous report.', 'wp-simple-firewall' ),
					'dates_below' => __( 'The information provided is for the dates below.', 'wp-simple-firewall' ),
					'dates'       => sprintf( '%s - %s',
						$oWP->getTimeStringForDisplay( $this->rep->interval_start_at ),
						$oWP->getTimeStringForDisplay( $this->rep->interval_end_at )
					),
				],
			]
		);
	}
}
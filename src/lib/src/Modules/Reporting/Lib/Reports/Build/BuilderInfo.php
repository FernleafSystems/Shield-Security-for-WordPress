<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Reporting;

class BuilderInfo extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$reports = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$repCon = $mod->getReportingHandler();
			if ( $repCon instanceof Reporting ) {
				foreach ( $repCon->getInfoReporters() as $reporter ) {
					$reports = array_merge(
						$reports,
						$reporter->setReport( $this->rep )->build()
					);
				}
			}
		}
		return $reports;
	}

	protected function render( array $aGatheredData ) :string {
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
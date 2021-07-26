<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Reporting;

class BuilderAlerts extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$reports = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$repCon = $mod->getReportingHandler();
			if ( $repCon instanceof Reporting ) {
				foreach ( $repCon->getAlertReporters() as $reporter ) {
					$reports = array_merge(
						$reports,
						$reporter->setReport( $this->rep )->build()
					);
				}
			}
		}
		return $reports;
	}

	protected function render( array $gathered ) :string {
		return $this->getMod()->renderTemplate(
			'/components/reports/alert_body.twig',
			[
				'vars'    => [
					'alerts' => $gathered
				],
				'strings' => [
					'title'    => __( 'Important Alerts', 'wp-simple-firewall' ),
					'subtitle' => __( 'The following is a collection of the latest alerts since your previous report.', 'wp-simple-firewall' ),
				],
			]
		);
	}
}
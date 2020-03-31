<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildAlerts {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() {
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
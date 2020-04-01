<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildInfo {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() {
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
		$aAlerts = [];
		foreach ( $this->getCon()->modules as $oMod ) {
			$oRepCon = $oMod->getReportingHandler();
			if ( $oRepCon instanceof BaseReporting ) {
				$aAlerts = array_merge(
					$aAlerts,
					$oRepCon->buildInfo()
				);
			}
		}
		return $aAlerts;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildAlerts {

	use ModConsumer;

	public function build() {
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
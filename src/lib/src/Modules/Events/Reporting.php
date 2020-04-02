<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildInfo( ReportVO $oRep ) {
		return ( new Reports\KeyStats() )
			->setMod( $this->getMod() )
			->buildInfo( $oRep );
	}
}
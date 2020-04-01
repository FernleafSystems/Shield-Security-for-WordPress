<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildInfo() {
		return ( new Reports\KeyStats() )
			->setMod( $this->getMod() )
			->buildInfo();
	}
}
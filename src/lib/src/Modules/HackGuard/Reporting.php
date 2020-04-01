<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildAlerts() {
		return array_merge(
			( new Reports\ScanAlerts() )
				->setMod( $this->getMod() )
				->buildAlerts(),
			( new Reports\FileLockerAlerts() )
				->setMod( $this->getMod() )
				->buildAlerts()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function buildInfo() {
		return array_merge(
			( new Reports\ScanAlerts() )
				->setMod( $this->getMod() )
				->buildAlerts(),
			( new Reports\FileLockerAlerts() )
				->setMod( $this->getMod() )
				->buildAlerts()
		);
	}
}
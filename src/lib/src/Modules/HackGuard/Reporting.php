<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	protected function enumAlertReporters() :array {
		return [
			new Reports\ScanAlerts(),
			new Reports\FileLockerAlerts(),
		];
	}
}
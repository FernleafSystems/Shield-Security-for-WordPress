<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

class Reporting extends Base\Reporting {

	/**
	 * @inheritDoc
	 */
	protected function enumAlertReporters() :array {
		return [
			new Reports\ScanAlerts(),
			new Reports\ScanRepairs(),
			new Reports\FileLockerAlerts(),
		];
	}
}
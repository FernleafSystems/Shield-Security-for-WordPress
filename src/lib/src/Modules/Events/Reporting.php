<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	protected function enumAlertReporters() :array {
		return [
			new Reports\ScanRepairs(),
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function enumInfoReporters() :array {
		return [
			new Reports\KeyStats(),
		];
	}
}
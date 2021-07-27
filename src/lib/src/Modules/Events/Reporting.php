<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

class Reporting extends Base\Reporting {

	/**
	 * @inheritDoc
	 */
	protected function enumInfoReporters() :array {
		return [
			new Reports\KeyStats(),
		];
	}
}
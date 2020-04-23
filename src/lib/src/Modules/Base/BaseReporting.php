<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

abstract class BaseReporting {

	use ModConsumer;

	/**
	 * @return Reports\BaseReporter[]
	 */
	public function enumAlertReporters() {
		return [];
	}

	/**
	 * @return Reports\BaseReporter[]
	 */
	public function enumInfoReporters() {
		return [];
	}
}
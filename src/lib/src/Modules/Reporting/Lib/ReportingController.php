<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BuildAlerts;

class ReportingController extends Base\OneTimeExecute {

	protected function run() {
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	public function runHourlyCron() {
	}
}
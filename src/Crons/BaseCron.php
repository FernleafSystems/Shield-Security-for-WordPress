<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseCron {

	use ExecOnce;
	use Shield\Crons\StandardCron;
	use PluginControllerConsumer;

	protected function run() {
		$this->setupCron();
	}
}
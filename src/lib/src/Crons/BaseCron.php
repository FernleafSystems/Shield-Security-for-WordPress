<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseCron {

	use Shield\Crons\StandardCron;
	use PluginControllerConsumer;

	public function run() {
		$this->setupCron();
	}
}
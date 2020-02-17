<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class BaseCron {

	use Shield\Crons\StandardCron;
	use Shield\Modules\ModConsumer;

	/**
	 */
	public function run() {
		$this->setupCron();
	}
}
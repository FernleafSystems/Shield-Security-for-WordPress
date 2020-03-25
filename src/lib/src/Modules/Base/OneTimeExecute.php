<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class OneTimeExecute {

	use ModConsumer;

	private $bExecuted = false;

	public function execute() {
		$this->bExecuted = false;
		$this->run();
		$this->bExecuted = true;
	}

	protected function run() {
	}
}
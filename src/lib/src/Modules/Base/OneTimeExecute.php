<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

trait OneTimeExecute {

	private $bExecuted = false;

	/**
	 * @return bool
	 */
	protected function canRun() {
		return true;
	}

	public function execute() {
		$this->bExecuted = false;
		if ( $this->canRun() ) {
			$this->run();
		}
		$this->bExecuted = true;
	}

	protected function run() {
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$mod->getScansCon()->execute();

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( count( $opts->getFilesToLock() ) > 0 ) {
			$mod->getFileLocker()->execute();
		}
	}

	public function runHourlyCron() {
	}

	public function runDailyCron() {
	}

	public function onWpLoaded() {
	}

	public function onModuleShutdown() {
	}
}
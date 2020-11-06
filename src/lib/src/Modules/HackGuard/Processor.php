<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	public function run() {
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
		( new Lib\Snapshots\StoreAction\TouchAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function runDailyCron() {
		( new Lib\Snapshots\StoreAction\CleanAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function onWpLoaded() {
		( new Lib\Snapshots\StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->hookBuild();
	}

	public function onModuleShutdown() {
		( new Lib\Snapshots\StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->schedule();
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\CreateNewScan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class QueueInit {

	use ModConsumer;

	/**
	 * Build and Enqueue.
	 * @param string $slug
	 * @throws \Exception
	 */
	public function init( string $slug ) {
		$this->preInit();
		$this->createScans( $slug );
	}

	private function preInit() {
		( new CleanQueue() )
			->setMod( $this->getMod() )
			->execute();
	}

	/**
	 * @throws \Exception
	 */
	private function createScans( string $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$scanRecord = ( new CreateNewScan() )
			->setMod( $mod )
			->run( $slug );

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $mod->getScanCon( $slug ) )
			->run();
	}
}

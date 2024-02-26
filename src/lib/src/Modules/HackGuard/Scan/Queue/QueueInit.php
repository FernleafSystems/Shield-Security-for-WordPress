<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\CreateNewScan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class QueueInit {

	use ModConsumer;

	/**
	 * Build and Enqueue.
	 * @throws \Exception
	 */
	public function init( string $slug ) {
		$this->preInit();
		$this->createScans( $slug );
	}

	private function preInit() {
		( new CleanQueue() )->execute();
	}

	/**
	 * @throws \Exception
	 */
	private function createScans( string $slug ) {
		( new PopulateScanItems() )
			->setRecord( ( new CreateNewScan() )->run( $slug ) )
			->setScanController( $this->mod()->getScansCon()->getScanCon( $slug ) )
			->run();
	}
}
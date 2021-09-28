<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;

class ProcessQueueItem {

	use Shield\Modules\ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( QueueItemVO $item ) :array {
		/** @var Shield\Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$action = ScanActionFromSlug::GetAction( $item->scan )
									->applyFromArray( $item->meta );
		$action->items = $item->items;

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->setMod( $mod )
			 ->run();

		if ( $action->usleep > 0 ) {
			usleep( $action->usleep );
		}

		return is_array( $action->results ) ? $action->results : [];
	}

	/**
	 * @param Shield\Scans\Base\BaseScanActionVO $action
	 * @return Shield\Scans\Base\BaseScan
	 */
	private function getScanner( $action ) {
		/** @var Shield\Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$class = $action->getScanNamespace().'Scan';
		/** @var Shield\Scans\Base\BaseScan $o */
		$o = new $class();
		return $o->setScanController( $mod->getScanCon( $action->scan ) )
				 ->setMod( $mod )
				 ->setScanActionVO( $action );
	}
}

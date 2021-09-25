<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Scan\Init\ScanQueueItemVO,
	Scan\ScanActionFromSlug
};

class ScanExecute {

	use Shield\Modules\ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function execute( ScanQueueItemVO $item ) :array {
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
		$class = $action->getScanNamespace().'Scan';
		/** @var Shield\Scans\Base\BaseScan $o */
		$o = new $class();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $action );
	}
}

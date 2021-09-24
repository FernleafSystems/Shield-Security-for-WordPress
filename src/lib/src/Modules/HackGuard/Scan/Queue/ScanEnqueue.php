<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanEnqueue {

	use ModConsumer;
	use QueueProcessorConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function enqueue() {
		$action = $this->getScanActionVO();
		$allItems = (array)$action->items;
		unset( $action->items );

		$nSliceSize = $action::QUEUE_GROUP_SIZE_LIMIT;

		do {
			$current = clone $action;
			$current->items = array_slice( $allItems, 0, $nSliceSize );
			$this->pushActionToQueue( $current );
			$allItems = array_slice( $allItems, $nSliceSize );
		} while ( !empty( $allItems ) );

		$this->getQueueProcessor()->save();
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $action
	 */
	protected function pushActionToQueue( $action ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$entry = ( new ConvertBetweenTypes() )
			->setMod( $mod )
			->fromActionToDbEntry( $action );
		$this->getQueueProcessor()->push_to_queue( $entry );
	}
}

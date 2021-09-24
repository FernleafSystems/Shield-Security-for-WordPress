<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanEnqueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanEnqueue {

	use ModConsumer;
	use QueueProcessorConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function enqueue() {
		$action = $this->getScanActionVO();
		$aAllItems = (array)$action->items;
		unset( $action->items );

		$nSliceSize = $action::QUEUE_GROUP_SIZE_LIMIT;

		do {
			$oCurrent = clone $action;
			$oCurrent->items = array_slice( $aAllItems, 0, $nSliceSize );
			$this->pushActionToQueue( $oCurrent );
			$aAllItems = array_slice( $aAllItems, $nSliceSize );
		} while ( !empty( $aAllItems ) );

		$this->getQueueProcessor()->save();
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $action
	 */
	protected function pushActionToQueue( $action ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$entry = ( new ConvertBetweenTypes() )
			->setDbHandler( $mod->getDbHandler_ScanQueue() )
			->fromActionToDbEntry( $action );
		$this->getQueueProcessor()->push_to_queue( $entry );
	}
}

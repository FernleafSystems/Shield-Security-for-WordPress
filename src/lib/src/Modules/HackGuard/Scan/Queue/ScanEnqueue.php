<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanEnqueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanEnqueue {

	use HandlerConsumer,
		QueueProcessorConsumer,
		Scans\Common\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function enqueue() {
		$oAction = $this->getScanActionVO();
		$aAllItems = (array)$oAction->items;
		unset( $oAction->items );

		$nSliceSize = $oAction::ITEM_STORAGE_LIMIT;

		do {
			$oCurrent = clone $oAction;
			$oCurrent->items = array_slice( $aAllItems, 0, $nSliceSize );
			$this->pushActionToQueue( $oCurrent );
			$aAllItems = array_slice( $aAllItems, $nSliceSize );
		} while ( !empty( $aAllItems ) );

		$this->getQueueProcessor()->save();
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $oAction
	 */
	protected function pushActionToQueue( $oAction ) {
		$oEntry = ( new ConvertBetweenTypes() )
			->setDbHandler( $this->getDbHandler() )
			->fromActionToDbEntry( $oAction );
		$this->getQueueProcessor()->push_to_queue( $oEntry );
	}
}

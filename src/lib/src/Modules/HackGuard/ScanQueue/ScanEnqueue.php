<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanEnqueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ScanEnqueue {

	use ModConsumer,
		QueueProcessorConsumer,
		Scans\Common\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function enqueue() {
		$oAction = $this->getScanActionVO();
		$aAllItems = (array)$oAction->items;

		$nSliceSize = $oAction::ITEM_STORAGE_LIMIT;

		do {
			$aSlice = array_slice( $aAllItems, 0, $nSliceSize );
			$oAction->items = $aSlice;
			$aAllItems = array_slice( $aAllItems, $nSliceSize );
			$this->pushActionToQueue( $oAction );
		} while ( !empty( $aAllItems ) );

		$this->getQueueProcessor()->save();
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $oAction
	 */
	protected function pushActionToQueue( $oAction ) {
		$oEntry = new EntryVO();
		foreach ( $this->getFields() as $sField ) {
			if ( isset( $oAction->{$sField} ) ) {
				$oEntry->{$sField} = $oAction->{$sField};
			}
		}
		unset( $oAction->items );
		$oEntry->meta = $oAction->getRawDataAsArray();

		$this->getQueueProcessor()->push_to_queue( $oEntry );
	}

	/**
	 * @return string[]
	 */
	private function getFields() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_ScanQueue();
		return $oDbH->getColumnsDefinition();
	}
}

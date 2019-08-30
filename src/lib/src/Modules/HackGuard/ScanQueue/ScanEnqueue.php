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

		$oQP = $this->getQueueProcessor();
		$nSliceSize = $oAction::ITEM_STORAGE_LIMIT;

		$aFields = $this->getFields();
		do {
			$aSlice = array_slice( $aAllItems, 0, $nSliceSize );
			$oAction->items = $aSlice;
			$aAllItems = array_slice( $aAllItems, $nSliceSize );

			$oEntry = new EntryVO();
			foreach ( $aFields as $sField ) {
				if ( isset( $oAction->{$sField} ) ) {
					$oEntry->{$sField} = $oAction->{$sField};
				}
			}
			$oQP->push_to_queue($oEntry);
		} while ( !empty( $aAllItems ) );

		$oQP->save();
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

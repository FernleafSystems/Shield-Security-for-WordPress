<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertBetweenTypes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ConvertBetweenTypes {

	use ModConsumer;

	/**
	 * @param ScanQueue\EntryVO $oEntry
	 * @return Scans\Base\BaseScanActionVO|mixed
	 */
	public function fromDbEntryToAction( $oEntry ) {
		$oScanAction = ( new ScanActionFromSlug() )->getAction( $oEntry->scan );
		$oScanAction->applyFromArray( $oEntry->meta );
		$oScanAction->items = $oEntry->items;
		$oScanAction->results = $oEntry->results;
		return $oScanAction;
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $oAction
	 * @return ScanQueue\EntryVO
	 */
	public function fromActionToDbEntry( $oAction ) {
		$oEntry = new ScanQueue\EntryVO();
		foreach ( $this->getDbEntryFields() as $sField ) {
			if ( isset( $oAction->{$sField} ) ) {
				$oEntry->{$sField} = $oAction->{$sField};
			}
		}
		unset( $oAction->items );
		unset( $oAction->results );
		$oEntry->meta = $oAction->getRawDataAsArray();
		return $oEntry;
	}

	/**
	 * @return string[]
	 */
	private function getDbEntryFields() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_ScanQueue();
		return $oDbH->getColumnsDefinition();
	}
}

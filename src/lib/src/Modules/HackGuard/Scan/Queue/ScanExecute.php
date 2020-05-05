<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanExecute
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanExecute {

	use Shield\Modules\ModConsumer;

	/**
	 * @param ScanQueue\EntryVO $oEntry
	 * @return ScanQueue\EntryVO
	 * @throws \Exception
	 */
	public function execute( $oEntry ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_ScanQueue();
		$oTypeConverter = ( new ConvertBetweenTypes() )->setDbHandler( $oDbH );

		$oAction = $oTypeConverter->fromDbEntryToAction( $oEntry );

		$this->getScanner( $oAction )
			 ->setScanActionVO( $oAction )
			 ->setMod( $oMod )
			 ->run();

		if ( $oAction->usleep > 0 ) {
			usleep( $oAction->usleep );
		}

		$oEntry->results = $oAction->results;

		return $oEntry;
	}

	/**
	 * @param Shield\Scans\Base\BaseScanActionVO $oAction
	 * @return Shield\Scans\Base\BaseScan
	 */
	private function getScanner( $oAction ) {
		$sClass = $oAction->getScanNamespace().'Scan';
		/** @var Shield\Scans\Base\BaseScan $o */
		$o = new $sClass();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $oAction );
	}
}

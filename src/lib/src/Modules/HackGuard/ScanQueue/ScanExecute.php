<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScanExecute
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ScanExecute {

	use Shield\Modules\ModConsumer,
		QueueProcessorConsumer;

	/**
	 * @param ScanQueue\EntryVO $oEntry
	 * @return ScanQueue\EntryVO
	 * @throws \Exception
	 */
	public function execute( $oEntry ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oTypeConverter = ( new ConvertBetweenTypes() )->setMod( $oMod );

		$oAction = $oTypeConverter->fromDbEntryToAction( $oEntry );

		$this->getScanner( $oAction )
			 ->setScanActionVO( $oAction )
			 ->setMod( $this->getMod() )
			 ->run();

		return $oTypeConverter->fromActionToDbEntry( $oAction );
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

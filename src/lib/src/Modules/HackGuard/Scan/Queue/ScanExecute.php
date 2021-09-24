<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

/**
 * Class ScanExecute
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanExecute {

	use Shield\Modules\ModConsumer;

	/**
	 * @param ScanQueue\EntryVO $entry
	 * @return ScanQueue\EntryVO
	 * @throws \Exception
	 */
	public function execute( $entry ) {
		/** @var Shield\Modules\HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$action = ( new ConvertBetweenTypes() )
			->setDbHandler( $mod->getDbHandler_ScanQueue() )
			->fromDbEntryToAction( $entry );

		$this->getScanner( $action )
			 ->setScanActionVO( $action )
			 ->setMod( $mod )
			 ->run();

		if ( $action->usleep > 0 ) {
			usleep( $action->usleep );
		}

		$entry->results = $action->results;
		return $entry;
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

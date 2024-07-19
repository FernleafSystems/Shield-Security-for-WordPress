<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller as ScanController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Afs,
	Common\ScanItemConsumer
};

class ItemRepairHandler {

	use PluginControllerConsumer;
	use ScanItemConsumer;

	/**
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		return $this->getRepairer()->repairItem();
	}

	/**
	 * @throws \Exception
	 */
	public function canRepairItem() :bool {
		return $this->isRepairSupported() && $this->getRepairer()->canRepair();
	}

	public function isRepairSupported() :bool {
		return $this->getScanItem()->VO->scan === ScanController\Afs::SCAN_SLUG;
	}

	/**
	 * @throws \Exception
	 */
	private function getRepairer() :Afs\Utilities\RepairItem {
		switch ( $this->getScanItem()->VO->scan ) {
			case ScanController\Afs::SCAN_SLUG:
				$rep = new Afs\Utilities\RepairItem();
				break;
			default:
				throw new \Exception( 'We never reach this point' );
		}
		return $rep->setScanItem( $this->getScanItem() );
	}
}
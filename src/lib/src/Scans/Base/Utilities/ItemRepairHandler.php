<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller as ScanController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Common\ScanItemConsumer,
	Afs,
	Mal,
	Ptg,
	Wcf
};

class ItemRepairHandler {

	use ModConsumer;
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
		return in_array(
			$this->getScanItem()->VO->scan,
			[ ScanController\Afs::SCAN_SLUG ]
		);
	}

	/**
	 * @return RepairItemBase
	 * @throws \Exception
	 */
	private function getRepairer() {

		switch ( $this->getScanItem()->VO->scan ) {

			case ScanController\Afs::SCAN_SLUG:
				$rep = new Afs\Utilities\RepairItem();
				break;

			default:
				throw new \Exception( 'We never reach this point' );
		}
		return $rep->setMod( $this->getMod() )
				   ->setScanItem( $this->getScanItem() );
	}
}
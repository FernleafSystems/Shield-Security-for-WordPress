<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller as ScanController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Common\ScanItemConsumer,
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
			[
				ScanController\Mal::SCAN_SLUG,
				ScanController\Ptg::SCAN_SLUG,
				ScanController\Wcf::SCAN_SLUG,
			]
		);
	}

	/**
	 * @return RepairItemBase
	 * @throws \Exception
	 */
	private function getRepairer() {

		switch ( $this->getScanItem()->VO->scan ) {

			case ScanController\Mal::SCAN_SLUG:
				$rep = new Mal\Utilities\RepairItem();
				break;

			case ScanController\Ptg::SCAN_SLUG:
				$rep = new Ptg\Utilities\RepairItem();
				break;

			case ScanController\Wcf::SCAN_SLUG:
				$rep = new Wcf\Utilities\RepairItem();
				break;

			default:
				throw new \Exception( 'We never reach this point' );
		}
		return $rep->setMod( $this->getMod() )
				   ->setScanItem( $this->getScanItem() );
	}
}
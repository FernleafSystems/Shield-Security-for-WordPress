<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;
use FernleafSystems\Wordpress\Services\Services;

class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @inheritDoc
	 */
	public function repairItem() :bool {
		throw new \Exception( 'Repair action is not supported' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();
		return (bool)Services::WpFs()->exists( $item->path_full );
	}
}
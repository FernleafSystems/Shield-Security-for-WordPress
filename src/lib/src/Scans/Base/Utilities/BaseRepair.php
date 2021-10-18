<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

abstract class BaseRepair {

	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		throw new \Exception( 'Item Repair is not supported' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return false;
	}
}
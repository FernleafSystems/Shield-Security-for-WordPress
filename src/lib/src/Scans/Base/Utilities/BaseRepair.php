<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

/**
 * Class BaseRepair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseRepair {

	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem() :bool;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteItem() :bool {
		throw new \Exception( 'Delete is not supported' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return false;
	}
}
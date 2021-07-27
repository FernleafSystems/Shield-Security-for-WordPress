<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\Utilities
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		throw new \Exception( 'Repair action is not supported' );
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class Repair extends Scans\Base\BaseRepair {

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 * @deprecated unused yet
	 */
	public function repairItem( $oItem ) {

		return true;
	}
}
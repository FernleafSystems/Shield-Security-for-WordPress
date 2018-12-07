<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class Repair extends Scans\Base\BaseRepair {

	/**
	 * @deprecated unused yet
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem( $oItem ) {

		return true;
	}
}
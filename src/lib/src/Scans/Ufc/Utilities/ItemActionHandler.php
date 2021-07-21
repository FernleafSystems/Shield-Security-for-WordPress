<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->delete();
	}

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )->setScanItem( $this->getScanItem() );
	}
}
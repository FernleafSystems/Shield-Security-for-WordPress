<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )->setScanItem( $this->getScanItem() );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->repair();
	}
}

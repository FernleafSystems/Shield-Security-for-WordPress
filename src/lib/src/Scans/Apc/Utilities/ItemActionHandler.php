<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )->setScanItem( $this->getScanItem() );
	}

	/**
	 * @param bool $success
	 */
	protected function fireRepairEvent( $success ) {
	}
}

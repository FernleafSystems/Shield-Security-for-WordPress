<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ItemActionHandler extends Base\Utilities\ItemActionHandlerAssets {

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )->setScanItem( $this->getScanItem() );
	}
}

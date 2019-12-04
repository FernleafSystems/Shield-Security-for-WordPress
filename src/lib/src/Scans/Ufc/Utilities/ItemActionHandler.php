<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )->setScanItem( $this->getScanItem() );
	}

	/**
	 * @param bool $bSuccess
	 */
	protected function fireRepairEvent( $bSuccess ) {
		/** @var Ufc\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanActionVO()->scan.'_item_repair_'.( $bSuccess ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_fragment ] ]
		);
	}
}

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
	 * @param bool $bSuccess
	 */
	protected function fireRepairEvent( $bSuccess ) {
		/** @var Wcf\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanController()->getSlug().'_item_repair_'.( $bSuccess ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_fragment ] ]
		);
	}
}

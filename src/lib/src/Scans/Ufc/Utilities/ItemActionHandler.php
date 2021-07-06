<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->repair( true );
	}

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
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanController()->getSlug().'_item_repair_'.( $success ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $item->path_full ] ]
		);
	}
}
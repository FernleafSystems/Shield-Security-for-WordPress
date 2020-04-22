<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function delete() {
		return $this->getRepairer()
					->setAllowDelete( true )
					->repairItem();
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() {
		parent::ignore();

		( new FalsePositiveReporter() )
			->setMod( $this->getMod() )
			->reportResultItem( $this->getScanItem(), true );

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repair() {
		return $this->getRepairer()
					->setAllowDelete( false )
					->repairItem();
	}

	/**
	 * @return Repair
	 */
	public function getRepairer() {
		return ( new Repair() )
			->setScanItem( $this->getScanItem() )
			->setMod( $this->getMod() );
	}

	/**
	 * @param bool $bSuccess
	 */
	protected function fireRepairEvent( $bSuccess ) {
		/** @var Mal\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanController()->getSlug().'_item_repair_'.( $bSuccess ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_fragment ] ]
		);
	}
}

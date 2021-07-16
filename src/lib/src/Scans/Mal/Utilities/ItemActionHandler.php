<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

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
	public function repairDelete() :bool {
		/** @var Mal\ResultItem $item */
		$item = $this->getScanItem();
		$repairer = $this->getRepairer();

		if ( $repairer->canRepair() ) {
			$repairer->repairItem();
		}
		else {
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
			$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
			$success = empty( $plugin ) && empty( $theme )
					   && $repairer->setAllowDelete( true )->repairItem();
		}

		return $success;
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
	 * @param bool $success
	 */
	protected function fireRepairEvent( $success ) {
		/** @var Mal\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanController()->getSlug().'_item_repair_'.( $success ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_full ] ]
		);
	}
}

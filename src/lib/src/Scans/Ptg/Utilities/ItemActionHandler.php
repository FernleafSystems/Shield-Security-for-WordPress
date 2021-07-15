<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class ItemActionHandler extends Base\Utilities\ItemActionHandlerAssets {

	/**
	 * @param string $action
	 * @return bool
	 * @throws \Exception
	 */
	public function process( $action ) {
		switch ( $action ) {

			case 'asset_accept':
				$success = $this->assetAccept();
				break;

			case 'asset_reinstall':
				$success = $this->assetReinstall();
				break;

			default:
				$success = parent::process( $action );
				break;
		}

		return $success;
	}

	/**
	 * @return true
	 * @throws \Exception
	 */
	private function assetAccept() {
		/** @var Ptg\ResultsSet $results */
		$results = $this->getScanController()->getAllResults();

		/** @var Ptg\ResultItem $item */
		$item = $this->getScanItem();

		foreach ( $results->getItemsForSlug( $item->slug ) as $item ) {
			$tmpHandler = clone $this;
			$tmpHandler->setScanItem( $item )
					   ->ignore();
		}

		( new Snapshots\StoreAction\Build() )
			->setMod( $this->getMod() )
			->setAsset( $this->getAssetFromSlug( $item->slug ) )
			->run();

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetReinstall() {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$success = false;

		$WPP = Services::WpPlugins();
		$WPT = Services::WpThemes();
		if ( $WPP->isInstalled( $item->slug ) ) {
			$asset = $WPP->getPluginAsVo( $item->slug );
			if ( $asset->isWpOrg() ) {
				$success = $WPP->reinstall( $item->slug );
			}
		}
		elseif ( $WPT->isInstalled( $item->slug ) ) {
			$asset = $WPT->getThemeAsVo( $item->slug );
			if ( $asset->isWpOrg() ) {
				$success = $WPT->reinstall( $item->slug );
			}
		}

		if ( $success ) {
			try {
				( new Snapshots\StoreAction\Build() )
					->setMod( $this->getMod() )
					->setAsset( $this->getAssetFromSlug( $item->slug ) )
					->run();
			}
			catch ( \Exception $e ) {
			}
		}

		return $success;
	}

	/**
	 * Repair PTG item if it's repairable, or it's unrecognised (i.e. delete)
	 * @return bool
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		$repairer = $this->getRepairer();

		if ( $repairer->canRepair() ) {
			$success = $repairer->repairItem();
		}
		elseif ( $item->is_unrecognised ) {
			$success = $repairer->setAllowDelete( true )->repairItem();
		}
		else {
			$success = false;
		}

		return $success;
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
		/** @var Ptg\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanController()->getSlug().'_item_repair_'.( $success ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_full ] ]
		);
	}
}

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
				$bSuccess = $this->assetAccept();
				break;

			case 'asset_reinstall':
				$bSuccess = $this->assetReinstall();
				break;

			default:
				$bSuccess = parent::process( $action );
				break;
		}

		return $bSuccess;
	}

	/**
	 * @return true
	 * @throws \Exception
	 */
	private function assetAccept() {
		/** @var Ptg\ResultsSet $oRes */
		$oRes = $this->getScanController()->getAllResults();

		/** @var Ptg\ResultItem $oMainItem */
		$oMainItem = $this->getScanItem();

		foreach ( $oRes->getItemsForSlug( $oMainItem->slug ) as $oItem ) {
			$oTmpHandler = clone $this;
			$oTmpHandler->setScanItem( $oItem )
						->ignore();
		}

		( new Snapshots\StoreAction\Build() )
			->setMod( $this->getMod() )
			->setAsset( $this->getAssetFromSlug( $oMainItem->slug ) )
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

<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class ItemActionHandler extends Base\Utilities\ItemActionHandlerAssets {

	/**
	 * @param string $sAction
	 * @return bool
	 * @throws \Exception
	 */
	public function handleAction( $sAction ) {
		switch ( $sAction ) {

			case 'asset_accept':
				$bSuccess = $this->assetAccept();
				break;

			case 'asset_reinstall':
				$bSuccess = $this->assetReinstall();
				break;

			default:
				$bSuccess = parent::handleAction( $sAction );
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
		/** @var ResultItem $oItem */
		$oItem = $this->getScanItem();

		$bSuccess = false;

		$oWpP = Services::WpPlugins();
		$oWpT = Services::WpThemes();
		if ( $oWpP->isInstalled( $oItem->slug ) ) {
			$oAsset = $oWpP->getPluginAsVo( $oItem->slug );
			if ( $oAsset->isWpOrg() ) {
				$bSuccess = $oWpP->reinstall( $oItem->slug );
			}
		}
		elseif ( $oWpT->isInstalled( $oItem->slug ) ) {
			$oAsset = $oWpT->getThemeAsVo( $oItem->slug );
			if ( $oAsset->isWpOrg() ) {
				$bSuccess = $oWpT->reinstall( $oItem->slug );
			}
		}

		if ( $bSuccess ) {
			try {
				( new Snapshots\StoreAction\Build() )
					->setMod( $this->getMod() )
					->setAsset( $this->getAssetFromSlug( $oItem->slug ) )
					->run();
			}
			catch ( \Exception $oE ) {
			}
		}

		return $bSuccess;
	}

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
		/** @var Ptg\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$this->getCon()->fireEvent(
			$this->getScanActionVO()->scan.'_item_repair_'.( $bSuccess ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_fragment ] ]
		);
	}
}

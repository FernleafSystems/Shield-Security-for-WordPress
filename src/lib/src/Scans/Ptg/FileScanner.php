<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @var Lib\Snapshots\Store
	 */
	private $oAssetStore;

	/**
	 * @param string $fullPath - in this case it's relative to ABSPATH
	 * @return ResultItem|null
	 */
	public function scan( $fullPath ) {
		$oItem = null;
		// file paths are stored in the queue relatives to ABSPATH
		$fullPath = path_join( wp_normalize_path( ABSPATH ), $fullPath );
		try {
			$oAsset = ( new Plugin\Files() )->findPluginFromFile( $fullPath );
			if ( empty( $oAsset ) ) {
				$oAsset = ( new Theme\Files() )->findThemeFromFile( $fullPath );
			}
			if ( empty( $oAsset ) ) {
				throw new \Exception( 'Could not load asset' );
			}

			$aHashes = $this->getHashes( $oAsset );
			$sPathFragment = str_replace( $oAsset->getInstallDir(), '', $fullPath );
			if ( empty( $aHashes[ $sPathFragment ] ) ) {
				$oItem = $this->getNewItem( $oAsset, $fullPath );
				$oItem->path_fragment = $sPathFragment;
				$oItem->is_unrecognised = true;
			}
			elseif ( !( new CompareHash() )->isEqualFileMd5( $fullPath, $aHashes[ $sPathFragment ] ) ) {
				$oItem = $this->getNewItem( $oAsset, $fullPath );
				$oItem->path_fragment = $sPathFragment;
				$oItem->is_different = true;
			}
		}
		catch ( \Exception $oE ) {
			error_log( $oE->getMessage() );
		}

		return $oItem;
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return string[]
	 * @throws \Exception
	 */
	private function getHashes( $oAsset ) {
		return $this->getStore( $oAsset )->getSnapData();
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return Lib\Snapshots\Store
	 * @throws \Exception
	 */
	private function getStore( $oAsset ) {

		// Re-Use the previous store if it's for the same Asset.
		if ( !empty( $this->oAssetStore ) ) {
			$sUniqueId = ( $oAsset instanceof VOs\WpPluginVo ) ? $oAsset->file : $oAsset->stylesheet;
			$aMeta = $this->oAssetStore->getSnapMeta();
			if ( $sUniqueId !== $aMeta[ 'unique_id' ] ) {
				unset( $this->oAssetStore );
			}
		}

		if ( empty( $this->oAssetStore ) ) {
			$this->oAssetStore = ( new Lib\Snapshots\StoreAction\Load() )
				->setMod( $this->getMod() )
				->setAsset( $oAsset )
				->run();
		}

		return $this->oAssetStore;
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @param string                       $sFile
	 * @return ResultItem
	 */
	private function getNewItem( $oAsset, $sFile ) {
		/** @var ResultItem $oItem */
		$oItem = $this->getScanActionVO()->getNewResultItem();
		$oItem->path_full = $sFile;
		$oItem->path_fragment = $sFile; // will eventually be overwritten
		$oItem->is_unrecognised = false;
		$oItem->is_different = false;
		$oItem->is_missing = false;
		$oItem->context = ( $oAsset instanceof VOs\WpPluginVo ) ? 'plugins' : 'themes';
		$oItem->slug = ( $oAsset instanceof VOs\WpPluginVo ) ? $oAsset->file : $oAsset->stylesheet;
		return $oItem;
	}
}
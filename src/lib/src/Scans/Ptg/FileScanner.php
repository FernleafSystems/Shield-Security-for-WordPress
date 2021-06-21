<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets;
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
	public function scan( string $fullPath ) {
		$item = null;
		// file paths are stored in the queue relatives to ABSPATH
		$fullPath = path_join( wp_normalize_path( ABSPATH ), $fullPath );
		try {
			$asset = ( new Plugin\Files() )->findPluginFromFile( $fullPath );
			if ( empty( $asset ) ) {
				$asset = ( new Theme\Files() )->findThemeFromFile( $fullPath );
			}
			if ( empty( $asset ) ) {
				throw new \Exception( sprintf( 'Could not load asset for: %s', $fullPath ) );
			}

			$assetHashes = $this->getHashes( $asset );
			$pathFragment = str_replace( $asset->getInstallDir(), '', $fullPath );
			if ( empty( $assetHashes[ $pathFragment ] ) ) {
				$item = $this->getNewItem( $asset, $fullPath );
				$item->path_fragment = $pathFragment;
				$item->is_unrecognised = true;
			}
			elseif ( !( new CompareHash() )->isEqualFileMd5( $fullPath, $assetHashes[ $pathFragment ] ) ) {
				$item = $this->getNewItem( $asset, $fullPath );
				$item->path_fragment = $pathFragment;
				$item->is_different = true;
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $item;
	}

	/**
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $oAsset
	 * @return string[]
	 * @throws \Exception
	 */
	private function getHashes( $oAsset ) {
		return $this->getStore( $oAsset )->getSnapData();
	}

	/**
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $asset
	 * @return Lib\Snapshots\Store
	 * @throws \Exception
	 */
	private function getStore( $asset ) {

		// Re-Use the previous store if it's for the same Asset.
		if ( !empty( $this->oAssetStore ) ) {
			$sUniqueId = ( $asset instanceof Assets\WpPluginVo ) ? $asset->file : $asset->stylesheet;
			$aMeta = $this->oAssetStore->getSnapMeta();
			if ( $sUniqueId !== $aMeta[ 'unique_id' ] ) {
				unset( $this->oAssetStore );
			}
		}

		if ( empty( $this->oAssetStore ) ) {
			$this->oAssetStore = ( new Lib\Snapshots\StoreAction\Load() )
				->setMod( $this->getMod() )
				->setAsset( $asset )
				->run();
		}

		return $this->oAssetStore;
	}

	/**
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $oAsset
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
		$oItem->context = ( $oAsset instanceof Assets\WpPluginVo ) ? 'plugins' : 'themes';
		$oItem->slug = ( $oAsset instanceof Assets\WpPluginVo ) ? $oAsset->file : $oAsset->stylesheet;
		return $oItem;
	}
}
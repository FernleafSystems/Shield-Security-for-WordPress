<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromApi;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\ApiPing;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oPluginHashes;

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oThemeHashes;

	/**
	 * @var bool
	 */
	private static $bCanUseLiveHashes = null;

	/**
	 * @param string $sFullPath - in this case it's relative to ABSPATH
	 * @return ResultItem|null
	 */
	public function scan( $sFullPath ) {
		$oItem = null;
		$sFullPath = path_join( wp_normalize_path( ABSPATH ), $sFullPath );
		try {
			$oAsset = ( new Plugin\Files() )->findPluginFromFile( $sFullPath );
			if ( empty( $oAsset ) ) {
				$oAsset = ( new Theme\Files() )->findThemeFromFile( $sFullPath );
			}
			if ( empty( $oAsset ) ) {
				throw new \Exception( 'Could not load asset' );
			}

			$aHashes = $this->getHashes( $oAsset );
			$sPathFragment = str_replace( $oAsset->getInstallDir(), '', $sFullPath );
			if ( empty( $aHashes[ $sPathFragment ] ) ) {
				$oItem = $this->getNewItem( $oAsset, $sFullPath );
				$oItem->path_fragment = $sPathFragment;
				$oItem->is_unrecognised = true;
			}
			elseif ( !( new CompareHash() )->isEqualFileMd5( $sFullPath, $aHashes[ $sPathFragment ] ) ) {
				$oItem = $this->getNewItem( $oAsset, $sFullPath );
				$oItem->path_fragment = $sPathFragment;
				$oItem->is_different = true;
			}
		}
		catch ( \Exception $oE ) {
		}

		return $oItem;
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return string[]
	 */
	private function getHashes( $oAsset ) {
		$aHashes = null;
		if ( is_null( self::$bCanUseLiveHashes ) ) {
			self::$bCanUseLiveHashes = ( new ApiPing() )->ping();
		}

		if ( self::$bCanUseLiveHashes ) {
			try {
				$aHashes = $this->getLiveHashes( $oAsset );
			}
			catch ( \Exception $oE ) {
//				error_log( $oE->getMessage() );
			}
		}

		if ( empty( $aHashes ) ) {
			if ( $oAsset instanceof VOs\WpPluginVo ) {
				$aHashes = $this->getStorePlugins()
								->getSnapItem( $oAsset->file );
			}
			else {
				$aHashes = $this->getStoreThemes()
								->getSnapItem( $oAsset->stylesheet );
			}
		}

		return $aHashes;
	}

	/**
	 * @return Snapshots\Store
	 */
	private function getStorePlugins() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( empty( $this->oPluginHashes ) ) {
			$this->oPluginHashes = ( new Shield\Scans\Ptg\Snapshots\Store() )
				->setContext( 'plugins' )
				->setStorePath( $oAction->hashes_base_path );
		}
		return $this->oPluginHashes;
	}

	/**
	 * @return Snapshots\Store
	 */
	private function getStoreThemes() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( empty( $this->oThemeHashes ) ) {
			$this->oThemeHashes = ( new Shield\Scans\Ptg\Snapshots\Store() )
				->setContext( 'themes' )
				->setStorePath( $oAction->hashes_base_path );
		}
		return $this->oThemeHashes;
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return string[]
	 * @throws \Exception
	 */
	private function getLiveHashes( $oAsset ) {
		return ( new BuildHashesFromApi() )->build( $oAsset );
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
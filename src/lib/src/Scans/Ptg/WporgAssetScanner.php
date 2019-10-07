<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromApi;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class WporgAssetScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class WporgAssetScanner {

	use ScanActionConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	/**
	 * @return ResultsSet
	 * @throws \Exception
	 */
	public function scan() {
		$oResults = new ResultsSet();

		$oAsset = $this->getAsset();

		if ( $oAsset instanceof WpPluginVo ) {
			$sAssetDir = Services::WpPlugins()->getInstallationDir( $oAsset->file );
		}
		else if ( $oAsset instanceof WpThemeVo ) {
			$sAssetDir = $oAsset->wp_theme->get_stylesheet_directory();
		}
		else {
			throw new \Exception( 'Unsupported Asset Type' );
		}

		// Get live hashes if it's possible.
		$aLive = $this->getLiveHashes();

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oCompare = new CompareHash();
		$sAssetDir = trailingslashit( wp_normalize_path( $sAssetDir ) );
		try {
			$oDirIt = StandardDirectoryIterator::create( $sAssetDir, $oAction->scan_depth, $oAction->file_exts );
			$sAbsPath = wp_normalize_path( ABSPATH );

			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				if ( !$oFile->isFile() ) {
					continue;
				}

				$sFullPath = wp_normalize_path( $oFile->getPathname() );
				$sRelAbsPath = str_replace( $sAbsPath, '', $sFullPath );
				$sPathFragment = str_replace( $sAssetDir, '', $sFullPath );

				if ( array_key_exists( $sRelAbsPath, $aLive ) ) {
					if ( !$oCompare->isEqualFileMd5( $sFullPath, $aLive[ $sRelAbsPath ] ) ) {
						$oItem = $this->getNewItem( $sFullPath );
						$oItem->path_fragment = $sPathFragment;
						$oItem->is_different = true;
						$oResults->addItem( $oItem );
					}
					unset( $aLive[ $sRelAbsPath ] );
				}
				else if ( !array_key_exists( $sRelAbsPath, $aLive ) ) {
					$oItem = $this->getNewItem( $sFullPath );
					$oItem->path_fragment = $sPathFragment;
					$oItem->is_unrecognised = true;
					$oResults->addItem( $oItem );
				}
			}

			// After looking at all files, now check for missing files.
			foreach ( $aLive as $sRelAbsPath => $sHash ) {
				$sFullPath = path_join( ABSPATH, $sRelAbsPath );
				$oItem = $this->getNewItem( $sFullPath );
				$oItem->path_fragment = str_replace( $sAssetDir, '', $sFullPath );
				$oItem->is_missing = true;
				$oResults->addItem( $oItem );
			}
		}
		catch ( \Exception $oE ) {
		}

		return $oResults;
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->oAsset;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $oAsset
	 * @return $this
	 */
	public function setAsset( $oAsset ) {
		$this->oAsset = $oAsset;
		return $this;
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	private function getLiveHashes() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return ( new BuildHashesFromApi() )
			->setDepth( $oAction->scan_depth )
			->setFileExts( $oAction->file_exts )
			->build( $this->getAsset() );
	}

	/**
	 * @param string $sFile
	 * @return ResultItem
	 */
	private function getNewItem( $sFile ) {
		$oAsset = $this->getAsset();
		$oItem = new ResultItem();
		$oItem->path_full = $sFile;
		$oItem->path_fragment = $sFile; // will eventually be overwritten
		$oItem->is_unrecognised = false;
		$oItem->is_different = false;
		$oItem->is_missing = false;
		$oItem->context = ( $oAsset instanceof WpPluginVo ) ? 'plugins' : 'themes';
		$oItem->slug = ( $oAsset instanceof WpPluginVo ) ? $oAsset->file : $oAsset->stylesheet;
		return $oItem;
	}
}
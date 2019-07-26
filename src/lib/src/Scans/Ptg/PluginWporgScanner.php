<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromApi;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class PluginWporgScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class PluginWporgScanner {

	use ScanActionConsumer;

	/**
	 * @param string $sSlug - plugin base file
	 * @return ResultsSet
	 * @throws \Exception
	 */
	public function scan( $sSlug ) {
		$oResults = new ResultsSet();

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$aLive = ( new BuildHashesFromApi() )
			->setDepth( $oAction->scan_depth )
			->setFileExts( $oAction->file_exts )
			->build( $sSlug );

		$oWpPlugins = Services::WpPlugins();
		$sAssetDir = trailingslashit( $oWpPlugins->getInstallationDir( $sSlug ) );
		$oCompare = new CompareHash();
		try {

			$oDirIt = StandardDirectoryIterator::create(
				$sAssetDir, $oAction->scan_depth, $oAction->file_exts );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				if ( !$oFile->isFile() ) {
					continue;
				}

				$sFullPath = wp_normalize_path( $oFile->getPathname() );
				$sRelAbsPath = str_replace( wp_normalize_path( ABSPATH ), '', $sFullPath );
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
	 * @param string $sFile
	 * @return ResultItem
	 */
	private function getNewItem( $sFile ) {
		$oItem = new ResultItem();
		$oItem->path_full = $sFile;
		$oItem->path_fragment = $sFile; // will eventually be overwritten
		$oItem->is_unrecognised = false;
		$oItem->is_different = false;
		$oItem->is_missing = false;
		return $oItem;
	}
}
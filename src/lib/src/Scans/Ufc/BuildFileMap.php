<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class BuildFileMap {

	use ScanActionConsumer;

	/**
	 * @return string[]
	 */
	public function build() {
		$aFiles = [];
		$oHashes = Services::CoreFileHashes();
		if ( !$oHashes->isReady() ) {
			return $aFiles;
		}

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		foreach ( $oAction->scan_dirs as $sScanDir => $aFileExts ) {
			try {
				/**
				 * The filter handles the bulk of the file inclusions and exclusions
				 * We can set the types (extensions) of the files to include
				 * useful for the upload directory where we're only interested in JS and PHP
				 * The filter will also be responsible (in this case) for filtering out
				 * WP Core files from the collection of files to be assessed
				 */
				foreach ( StandardDirectoryIterator::create( $sScanDir, 0, $aFileExts, true ) as $oFsItem ) {
					/** @var \SplFileInfo $oFsItem */
					$sFullPath = $oFsItem->getPathname();
					if ( !$oHashes->isCoreFile( $sFullPath ) ) {
						$aFiles[] = wp_normalize_path( $sFullPath );
					}
				}
			}
			catch ( \Exception $oE ) {
				error_log(
					sprintf( 'Shield file scanner (%s) attempted to read directory (%s) but there was error: "%s".',
						$oAction->scan, $sScanDir, $oE->getMessage() )
				);
			}
		}
		return $aFiles;
	}
}
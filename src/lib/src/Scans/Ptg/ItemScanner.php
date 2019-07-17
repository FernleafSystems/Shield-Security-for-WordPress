<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanActionConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromDir;

/**
 * Class ItemScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ItemScanner {

	use ScanActionConsumer;

	/**
	 * @param string $sRootDir
	 * @param array  $aExistingHashes
	 * @return ResultsSet
	 */
	public function scan( $sRootDir, $aExistingHashes ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		// 1 Build hashes from dir
		$aNewHashes = ( new BuildHashesFromDir() )
			->setFileExts( $oAction->file_exts )
			->setDepth( $oAction->scan_depth )
			->build( $sRootDir );

		// 2 Compare new hashes with existing hashes to build scan results
		$oResultsSet = ( new DiffHashes() )->diff( $aExistingHashes, $aNewHashes );

		// 3 Add the path_fragment to each result item in the set
		// So as to include the plugin or theme part
		$sPrefixToChop = wp_normalize_path( $sRootDir );
		foreach ( $oResultsSet->getAllItems() as $oItem ) {
			/** @var ResultItem $oItem */
			$oItem->path_fragment = str_replace( $sPrefixToChop, '', wp_normalize_path( $oItem->path_full ) );
		}

		return $oResultsSet;
	}
}
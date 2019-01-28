<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromDir;

/**
 * Class ScanAssetDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ScanAssetDir {

	/**
	 * @var int
	 */
	protected $nDepth = 0;

	/**
	 * @var string[]
	 */
	protected $aFileExts = array();

	/**
	 * @param string $sRootDir
	 * @param array  $aExistingHashes
	 * @return ResultsSet
	 */
	public function run( $sRootDir, $aExistingHashes ) {

		// 1 Build hashes from dir
		$aNewHashes = ( new BuildHashesFromDir() )
			->setFileExts( $this->aFileExts )
			->setDepth( $this->nDepth )
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

	/**
	 * @param int $nDepth
	 * @return $this
	 */
	public function setDepth( $nDepth ) {
		$this->nDepth = max( 0, (int)$nDepth );
		return $this;
	}

	/**
	 * @param string[] $aExts
	 * @return $this
	 */
	public function setFileExts( $aExts ) {
		$this->aFileExts = $aExts;
		return $this;
	}
}
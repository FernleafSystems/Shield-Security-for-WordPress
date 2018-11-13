<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromDir;

/**
 * Class ScanAssetDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
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
	 * @param array $aExistingHashes
	 * @return ResultsSet
	 */
	public function run( $sRootDir, $aExistingHashes ) {

		// 1 Build hashes from dir
		$aNewHashes = ( new BuildHashesFromDir() )
			->setFileExts( $this->aFileExts )
			->setDepth( $this->nDepth )
			->build( $sRootDir );

		// 2 Compare new hashes with existing hashes.
		return ( new DiffHashes() )->diff( $aExistingHashes, $aNewHashes );
	}

	/**
	 * @param int $nDepth
	 * @return $this
	 */
	public function setDepth( $nDepth ) {
		$this->nDepth = min( 0, (int)$nDepth );
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
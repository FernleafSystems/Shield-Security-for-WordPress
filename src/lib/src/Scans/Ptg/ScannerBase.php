<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScannerBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
abstract class ScannerBase {

	const CONTEXT = '';

	/**
	 * @var int
	 */
	protected $nDepth = 0;

	/**
	 * @var string[]
	 */
	protected $aFileExts = array();

	/**
	 * @param array[] $aPreExistingHashes - key is the slug/base-file name and value is the file hashes
	 * @return ResultsSet
	 */
	public function run( $aPreExistingHashes ) {
		$oFinalResults = new ResultsSet();

		$oCopy = new Scans\Helpers\CopyResultsSets();
		$oAssetScanner = ( new ScanAssetDir() )
			->setFileExts( $this->aFileExts )
			->setDepth( $this->nDepth );

		foreach ( $aPreExistingHashes as $sSlug => $aExHashes ) {
			// Build all-new hashes of item directory and scan it
			$oRes = $oAssetScanner->run( $this->getDirFromItemSlug( $sSlug ), $aExHashes )
								  ->setSlugOnAllItems( $sSlug )
								  ->setContextOnAllItems( static::CONTEXT );
			// Copy results to final results
			$oCopy->copyTo( $oRes, $oFinalResults );
		}

		return $oFinalResults;
	}

	/**
	 * @param string $sAssetSlug
	 * @return string[]
	 */
	public function hashAssetFiles( $sAssetSlug ) {
		return ( new Scans\Helpers\BuildHashesFromDir() )
			->setFileExts( $this->aFileExts )
			->setDepth( $this->nDepth )
			->build( $this->getDirFromItemSlug( $sAssetSlug ) );
	}

	/**
	 * @param string $sSlug
	 * @return string
	 */
	abstract protected function getDirFromItemSlug( $sSlug );

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
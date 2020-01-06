<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScannerBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 * @deprecated 8.5
 */
abstract class ScannerBase {

	const CONTEXT = '';

	/**
	 * @param array[] $aPreExistingHashes - key is the slug/base-file name and value is the file hashes
	 * @return ResultsSet
	 */
	public function run( $aPreExistingHashes ) {
		return new ResultsSet();
	}

	/**
	 * @param string $sAssetSlug
	 * @return string[]
	 */
	public function hashAssetFiles( $sAssetSlug ) {
		return [];
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
		return $this;
	}

	/**
	 * @param string[] $aExts
	 * @return $this
	 */
	public function setFileExts( $aExts ) {
		return $this;
	}
}
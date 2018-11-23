<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

/**
 * Class BuildHashesFromDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
 */
class BuildHashesFromDir {

	/**
	 * @var int
	 */
	protected $nDepth = 0;

	/**
	 * @var string[]
	 */
	protected $aFileExts = array();

	/**
	 * @param string $sDir
	 * @return string[]
	 */
	public function build( $sDir ) {
		$aSnaps = array();
		try {
			$oDirIt = StandardDirectoryIterator::create( $sDir, $this->nDepth, $this->aFileExts );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				$aSnaps[ $oFile->getPathname() ] = md5_file( $oFile->getPathname() );
			}
		}
		catch ( \Exception $oE ) {
		}
		return $aSnaps;
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
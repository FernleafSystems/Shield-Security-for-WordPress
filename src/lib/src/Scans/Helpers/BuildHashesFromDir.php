<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

/**
 * Class BuildHashesFromDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
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
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param string $sDir
	 * @return string[]
	 */
	public function build( $sDir ) {
		$aSnaps = array();
		try {
			$oDirIt = StandardDirectoryIterator::create( $sDir, $this->nDepth, $this->aFileExts );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				$sFullPath = $oFile->getPathname();
				$sKey = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $sFullPath ) );
				$aSnaps[ $sKey ] = md5_file( $sFullPath );
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
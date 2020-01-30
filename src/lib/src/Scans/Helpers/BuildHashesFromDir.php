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
	protected $aFileExts = [];

	/**
	 * @var string
	 */
	private $sHashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param string $sDir
	 * @return string[]
	 */
	public function build( $sDir ) {
		$aSnaps = [];
		try {
			$sReplaceDir = wp_normalize_path( ABSPATH );
			$sAlgo = $this->getHashAlgo();
			$oDirIt = StandardDirectoryIterator::create( $sDir, $this->nDepth, $this->aFileExts );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				$sFullPath = $oFile->getPathname();
				$sKey = str_replace( $sReplaceDir, '', wp_normalize_path( $sFullPath ) );
				$aSnaps[ $sKey ] = hash_file( $sAlgo, $sFullPath );
			}
		}
		catch ( \Exception $oE ) {
		}
		return $aSnaps;
	}

	/**
	 * @return string
	 */
	public function getHashAlgo() {
		return empty( $this->sHashAlgo ) ? 'md5' : $this->sHashAlgo;
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

	/**
	 * @param string $sHashAlgo
	 * @return static
	 */
	public function setHashAlgo( $sHashAlgo ) {
		$this->sHashAlgo = $sHashAlgo;
		return $this;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildHashesFromDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build
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
	 * @param string $dir
	 * @return string[]
	 */
	public function build( $dir, bool $binary = false ) {
		$aSnaps = [];
		try {
			$dir = wp_normalize_path( $dir );
			$sAlgo = $this->getHashAlgo();
			$oDirIt = StandardDirectoryIterator::create( $dir, $this->nDepth, $this->aFileExts );
			foreach ( $oDirIt as $oFile ) {
				/** @var \SplFileInfo $oFile */
				$sFullPath = $oFile->getPathname();
				$sKey = str_replace( $dir, '', wp_normalize_path( $sFullPath ) );
				$aSnaps[ $sKey ] = hash_file( $sAlgo, $sFullPath, $binary );
			}
		}
		catch ( \Exception $e ) {
		}
		return $aSnaps;
	}

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param string $dir
	 * @return string[]
	 */
	public function buildNormalised( string $dir ) :array {
		$snaps = [];
		$DM = Services::DataManipulation();
		try {
			$dir = wp_normalize_path( $dir );
			$algo = $this->getHashAlgo();
			$dirIT = StandardDirectoryIterator::create( $dir, $this->nDepth, $this->aFileExts );
			foreach ( $dirIT as $oFile ) {
				/** @var \SplFileInfo $oFile */
				$fullPath = $oFile->getPathname();
				$key = str_replace( $dir, '', wp_normalize_path( $fullPath ) );
				$snaps[ $key ] = hash( $algo, $DM->convertLineEndingsDosToLinux( $fullPath ) );
			}
		}
		catch ( \Exception $e ) {
		}
		return $snaps;
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
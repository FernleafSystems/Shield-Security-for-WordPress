<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

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
		$snaps = [];
		try {
			$dir = wp_normalize_path( $dir );
			$sAlgo = $this->getHashAlgo();
			$oDirIt = StandardDirectoryIterator::create( $dir, $this->nDepth, $this->aFileExts );
			foreach ( $oDirIt as $file ) {
				/** @var \SplFileInfo $file */
				$fullPath = $file->getPathname();
				$key = str_replace( $dir, '', wp_normalize_path( $fullPath ) );
				$snaps[ strtolower( $key ) ] = hash_file( $sAlgo, $fullPath, $binary );
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
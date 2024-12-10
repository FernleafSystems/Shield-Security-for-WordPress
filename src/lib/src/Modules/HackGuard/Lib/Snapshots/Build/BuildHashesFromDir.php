<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

class BuildHashesFromDir {

	/**
	 * @var int
	 */
	protected $depth = 0;

	/**
	 * @var string[]
	 */
	protected $fileExtensions = [];

	/**
	 * @var string
	 */
	private $hashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, stripped of ABSPATH.
	 * @return string[]
	 */
	public function build( string $dir, bool $binary = false ) :array {
		$snaps = [];
		try {
			$dir = wp_normalize_path( $dir );
			$algo = $this->getHashAlgo();
			foreach ( StandardDirectoryIterator::create( $dir, $this->depth, $this->fileExtensions ) as $file ) {
				/** @var \SplFileInfo $file */
				$path = $file->getPathname();
				$snaps[ \strtolower( \str_replace( $dir, '', wp_normalize_path( $path ) ) ) ] =
					\hash_file( $algo, $path, $binary );
			}
		}
		catch ( \Exception $e ) {
		}
		return $snaps;
	}

	public function getHashAlgo() :string {
		return empty( $this->hashAlgo ) ? 'md5' : $this->hashAlgo;
	}

	/**
	 * @return $this
	 */
	public function setDepth( int $depth ) {
		$this->depth = (int)\max( 0, $depth );
		return $this;
	}

	/**
	 * @param string[] $exts
	 * @return $this
	 */
	public function setFileExts( array $exts ) {
		$this->fileExtensions = $exts;
		return $this;
	}

	/**
	 * @return static
	 */
	public function setHashAlgo( string $hashAlgo ) {
		$this->hashAlgo = $hashAlgo;
		return $this;
	}
}
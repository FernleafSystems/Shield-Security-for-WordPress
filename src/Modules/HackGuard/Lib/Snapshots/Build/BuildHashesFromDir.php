<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

class BuildHashesFromDir {
	protected int $depth = 0;

	/**
	 * @var string[]
	 */
	protected array $fileExtensions = [];

	private string $hashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, stripped of ABSPATH.
	 * @return string[]
	 */
	public function build( string $dir, bool $binary = false ): array {
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

	public function getHashAlgo(): string {
		return empty( $this->hashAlgo ) ? 'md5' : $this->hashAlgo;
	}

	public function setDepth( int $depth ) : self{
		$this->depth = (int)\max( 0, $depth );
		return $this;
	}

	/**
	 * @param string[] $exts
	 */
	public function setFileExts( array $exts ): self {
		$this->fileExtensions = $exts;
		return $this;
	}

	public function setHashAlgo( string $hashAlgo ): self {
		$this->hashAlgo = $hashAlgo;
		return $this;
	}
}
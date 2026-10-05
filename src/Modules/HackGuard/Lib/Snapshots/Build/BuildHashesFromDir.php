<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\FileHashAlgorithm;

class BuildHashesFromDir {
	protected int $depth = 0;

	/**
	 * @var string[]
	 */
	protected array $fileExtensions = [];

	private string $hashAlgo = 'md5';

	/**
	 * File keys are lowercased normalised paths with the supplied directory stripped.
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
				$hash = \hash_file( $algo, $path, $binary );
				if ( $hash === false ) {
					return [];
				}
				$snaps[ \strtolower( \str_replace( $dir, '', wp_normalize_path( $path ) ) ) ] = $hash;
			}
		}
		catch ( \Exception $e ) {
			$snaps = [];
		}
		return $snaps;
	}

	public function getHashAlgo(): string {
		return $this->hashAlgo;
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
		$this->hashAlgo = FileHashAlgorithm::validate( $hashAlgo );
		return $this;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use FernleafSystems\Wordpress\Services\Core\Fs;

class CacheStoreTestFs extends Fs {

	/**
	 * @var string[]
	 */
	public array $failedDirs = [];

	/**
	 * @var string[]
	 */
	public array $deletedDirs = [];

	public function failDir( string $dir ) :void {
		$this->failedDirs[] = $this->normalise( $dir );
	}

	public function exists( $path ) :?bool {
		return \file_exists( (string)$path );
	}

	public function mkdir( $path ) {
		$path = $this->normalise( (string)$path );
		return !$this->isFailed( $path ) && ( \is_dir( $path ) || @\mkdir( $path, 0777, true ) );
	}

	public function isDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function isFile( $path ) :bool {
		return \is_file( (string)$path );
	}

	public function isAccessibleDir( string $path ) :bool {
		return $path !== '' && !$this->isFailed( $path ) && \is_dir( $path );
	}

	public function isAccessibleFile( string $path ) :bool {
		return $path !== '' && \is_file( $path );
	}

	public function getAllFilesInDir( $dir, $includeDirs = true ) {
		$items = [];
		if ( \is_dir( (string)$dir ) ) {
			foreach ( new \DirectoryIterator( (string)$dir ) as $item ) {
				if ( !$item->isDot() && ( $item->isFile() || $includeDirs ) ) {
					$items[] = $this->normalise( $item->getPathname() );
				}
			}
		}
		return $items;
	}

	public function getFileContent( $path, $uncompress = false ) {
		$contents = \is_file( (string)$path ) ? \file_get_contents( (string)$path ) : null;
		if ( \is_string( $contents ) && $uncompress ) {
			$inflated = \gzinflate( $contents );
			return \is_string( $inflated ) ? $inflated : null;
		}
		return $contents;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$dir = \dirname( (string)$path );
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
		return \file_put_contents( (string)$path, $compress ? \gzdeflate( $contents ) : $contents ) !== false;
	}

	public function deleteDir( $dir ) {
		$dir = $this->normalise( (string)$dir );
		$this->deletedDirs[] = $dir;
		if ( !\is_dir( $dir ) ) {
			return true;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		return @\rmdir( $dir );
	}

	public function deleteFile( $path ) {
		return !\is_file( (string)$path ) || @\unlink( (string)$path );
	}

	public function getModifiedTime( string $path ) :int {
		return \is_file( $path ) || \is_dir( $path ) ? (int)\filemtime( $path ) : 0;
	}

	public function touch( $path, $time = null ) {
		$dir = \dirname( (string)$path );
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
		return \touch( (string)$path, $time ?? \time() );
	}

	public function normalise( string $path ) :string {
		return \str_replace( '\\', '/', \rtrim( $path, '/\\' ) );
	}

	private function isFailed( string $dir ) :bool {
		$dir = $this->normalise( $dir );
		foreach ( $this->failedDirs as $failedDir ) {
			if ( $dir === $failedDir || \str_starts_with( $dir.'/', $failedDir.'/' ) ) {
				return true;
			}
		}
		return false;
	}
}

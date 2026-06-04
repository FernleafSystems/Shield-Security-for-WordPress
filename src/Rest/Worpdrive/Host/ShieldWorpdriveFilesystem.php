<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\WorpdriveClient\Host\WorpdriveFilesystem;

class ShieldWorpdriveFilesystem implements WorpdriveFilesystem {

	private const PROBE_PREFIX = '.shield-worpdrive-probe-';

	private const RANDOM_WRITE_CHUNK_SIZE = 1048576;

	public function mkdir( $path ) :bool {
		return Services::WpFs()->mkdir( $path );
	}

	public function delete( $path ) {
		return Services::WpFs()->delete( (string)$path );
	}

	public function deleteFile( $path ) {
		return Services::WpFs()->deleteFile( $path );
	}

	public function deleteDir( $path ) {
		return Services::WpFs()->deleteDir( $path );
	}

	public function enumItemsInDir( string $dir ) :array {
		return Services::WpFs()->enumItemsInDir( $dir );
	}

	public function putFileContent( $path, $contents, bool $compress = false ) :bool {
		return Services::WpFs()->putFileContent( $path, $contents, $compress );
	}

	public function getFileContent( $path ) {
		return Services::WpFs()->getFileContent( $path );
	}

	public function isFile( $path ) :bool {
		return Services::WpFs()->isFile( $path );
	}

	public function isReadable( string $path ) :bool {
		$wpfs = Services::WpFs()->fs();
		return ( $wpfs && \method_exists( $wpfs, 'is_readable' ) && $wpfs->is_readable( $path ) )
			   || \is_readable( $path );
	}

	public function mtime( string $path ) :int {
		$wpfs = Services::WpFs()->fs();
		if ( $wpfs && \method_exists( $wpfs, 'mtime' ) ) {
			return (int)$wpfs->mtime( $path );
		}
		return (int)\filemtime( $path );
	}

	public function size( string $path ) :int {
		$wpfs = Services::WpFs()->fs();
		if ( $wpfs && \method_exists( $wpfs, 'size' ) ) {
			return (int)$wpfs->size( $path );
		}
		return (int)Services::WpFs()->getFileSize( $path );
	}

	public function canWriteToDir( string $dir ) :bool {
		$dir = $this->normalisePath( $dir );
		if ( !path_is_absolute( $dir ) || $dir === '/' ) {
			return false;
		}

		$probeFile = null;
		$probeFileCreated = false;
		$createdDirs = $this->missingDirChain( $dir );

		try {
			if ( !$this->mkdir( $dir ) || !$this->isDirPath( $dir ) || !$this->isWritable( $dir ) ) {
				return false;
			}

			$probeFile = $this->uniqueProbePath( $dir );
			$probeContent = \hash( 'sha256', \random_bytes( 16 ).$probeFile );
			if ( !$this->writeContentExclusive( $probeFile, $probeContent, $probeFileCreated ) ) {
				return false;
			}

			return $this->getFileContent( $probeFile ) === $probeContent;
		}
		catch ( \Throwable $e ) {
			return false;
		}
		finally {
			if ( $probeFileCreated && $probeFile !== null && $this->isFile( $probeFile ) ) {
				$this->deleteOwnedFile( $probeFile );
			}
			$this->deleteOwnedEmptyDirs( $createdDirs );
		}
	}

	public function writeRandomBytesFile( string $path, int $size ) :bool {
		if ( $size < 1 ) {
			return false;
		}

		$path = $this->normalisePath( $path );
		$dir = \dirname( $path );
		$probeFile = null;
		$probeFileCreated = false;
		$createdDirs = $this->missingDirChain( $dir );

		try {
			if ( !$this->mkdir( $dir ) || !$this->isDirPath( $dir ) || !$this->isWritable( $dir ) ) {
				return false;
			}

			$probeFile = $this->exists( $path ) ? $this->uniqueProbePath( $dir, \basename( $path ).'.probe-' ) : $path;
			if ( !$this->writeRandomBytesExclusive( $probeFile, $size, $probeFileCreated ) ) {
				return false;
			}

			return $this->deleteOwnedFile( $probeFile );
		}
		catch ( \Throwable $e ) {
			return false;
		}
		finally {
			if ( $probeFileCreated && $probeFile !== null && $this->isFile( $probeFile ) ) {
				$this->deleteOwnedFile( $probeFile );
			}
			$this->deleteOwnedEmptyDirs( $createdDirs );
		}
	}

	private function normalisePath( string $path ) :string {
		return wp_normalize_path( $path );
	}

	private function exists( string $path ) :bool {
		$exists = Services::WpFs()->exists( $path );
		return $exists === null ? \file_exists( $path ) : $exists;
	}

	private function isDirPath( string $path ) :bool {
		return Services::WpFs()->isDir( $path ) || \is_dir( $path );
	}

	private function isWritable( string $path ) :bool {
		$wpfs = Services::WpFs()->fs();
		return ( $wpfs && \method_exists( $wpfs, 'is_writable' ) && $wpfs->is_writable( $path ) )
			   || \is_writable( $path );
	}

	/**
	 * @return string[]
	 */
	private function missingDirChain( string $dir ) :array {
		$missing = [];
		$current = untrailingslashit( $dir );
		while ( $current !== '' && !$this->isDirPath( $current ) ) {
			$missing[] = $current;
			$parent = \dirname( $current );
			if ( $parent === $current || $parent === '.' || $parent === '' || \preg_match( '#^[A-Za-z]:$#', $parent ) === 1 ) {
				break;
			}
			$current = $parent;
		}
		return \array_reverse( $missing );
	}

	private function uniqueProbePath( string $dir, ?string $prefix = null ) :string {
		$prefix = $prefix ?? self::PROBE_PREFIX;
		for ( $attempt = 0 ; $attempt < 16 ; $attempt++ ) {
			$probeFile = path_join( $dir, $prefix.\bin2hex( \random_bytes( 12 ) ) );
			if ( !$this->exists( $probeFile ) ) {
				return $probeFile;
			}
		}
		throw new \RuntimeException( 'Unable to reserve a unique WorpDrive filesystem probe path.' );
	}

	private function writeContentExclusive( string $path, string $contents, bool &$created ) :bool {
		$created = false;
		$handle = @\fopen( $path, 'xb' );
		if ( !\is_resource( $handle ) ) {
			return false;
		}

		$created = true;
		$success = false;
		try {
			$success = $this->writeAll( $handle, $contents );
			return $success;
		}
		finally {
			\fclose( $handle );
			if ( !$success && $this->isFile( $path ) ) {
				$this->deleteOwnedFile( $path );
			}
		}
	}

	private function writeRandomBytesExclusive( string $path, int $size, bool &$created ) :bool {
		$created = false;
		$handle = @\fopen( $path, 'xb' );
		if ( !\is_resource( $handle ) ) {
			return false;
		}

		$created = true;
		$success = false;
		try {
			$remaining = $size;
			while ( $remaining > 0 ) {
				$chunkSize = \min( $remaining, self::RANDOM_WRITE_CHUNK_SIZE );
				if ( !$this->writeAll( $handle, \random_bytes( $chunkSize ) ) ) {
					return false;
				}
				$remaining -= $chunkSize;
			}
			$success = true;
			return true;
		}
		finally {
			\fclose( $handle );
			if ( !$success && $this->isFile( $path ) ) {
				$this->deleteOwnedFile( $path );
			}
		}
	}

	/**
	 * @param resource $handle
	 */
	private function writeAll( $handle, string $contents ) :bool {
		$length = \strlen( $contents );
		$written = 0;
		while ( $written < $length ) {
			$bytes = \fwrite( $handle, \substr( $contents, $written ) );
			if ( $bytes === false || $bytes < 1 ) {
				return false;
			}
			$written += $bytes;
		}
		return true;
	}

	private function deleteOwnedFile( string $path ) :bool {
		if ( !$this->isFile( $path ) ) {
			return true;
		}
		try {
			if ( $this->deleteFile( $path ) ) {
				return true;
			}
		}
		catch ( \Throwable $e ) {
		}
		return !\is_file( $path ) || @\unlink( $path );
	}

	/**
	 * @param string[] $dirs
	 */
	private function deleteOwnedEmptyDirs( array $dirs ) :void {
		foreach ( \array_reverse( $dirs ) as $dir ) {
			if ( $dir !== '' && \is_dir( $dir ) ) {
				@\rmdir( $dir );
			}
		}
	}
}

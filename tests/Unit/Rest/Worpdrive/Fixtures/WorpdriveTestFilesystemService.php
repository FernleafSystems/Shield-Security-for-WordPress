<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Fs;

class WorpdriveTestFilesystemService extends Fs {

	private ?WorpdriveTestFilesystemHandle $filesystem = null;

	public function fs() {
		return $this->filesystem ??= new WorpdriveTestFilesystemHandle();
	}

	public function mkdir( $path ) :bool {
		return \is_dir( (string)$path ) || @\mkdir( (string)$path, 0777, true );
	}

	public function delete( string $path ) :bool {
		return \is_dir( $path ) ? $this->deleteDir( $path ) : (bool)$this->deleteFile( $path );
	}

	public function deleteDir( $dir ) {
		if ( !\is_dir( (string)$dir ) ) {
			return true;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( (string)$dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		return @\rmdir( (string)$dir );
	}

	public function deleteFile( $path ) {
		return !\is_file( (string)$path ) || @\unlink( (string)$path );
	}

	public function enumItemsInDir( string $dir ) :array {
		return \glob( \rtrim( $dir, '/\\' ).'/*' ) ?: [];
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$this->mkdir( \dirname( (string)$path ) );
		return \file_put_contents( (string)$path, (string)$contents ) !== false;
	}

	public function getFileContent( $path, $uncompress = false ) {
		return \is_file( (string)$path ) ? \file_get_contents( (string)$path ) : null;
	}

	public function isDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function isFile( $path ) :bool {
		return \is_file( (string)$path );
	}

	public function exists( $path ) :?bool {
		return \file_exists( (string)$path );
	}

	public function isAccessibleDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function isAccessibleFile( string $path ) :bool {
		return \is_file( $path );
	}

	public function touch( $path, $time = null ) {
		return @\touch( (string)$path, $time ?? \time() );
	}

	public function getFileSize( $path ) :?int {
		return \is_file( (string)$path ) ? (int)\filesize( (string)$path ) : null;
	}
}

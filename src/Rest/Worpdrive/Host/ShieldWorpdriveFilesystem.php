<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Services\{
	Services,
	Utilities\File\AssessDirWrite,
	Utilities\File\DummyFile
};
use FernleafSystems\WorpdriveClient\Host\WorpdriveFilesystem;

class ShieldWorpdriveFilesystem implements WorpdriveFilesystem {

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
		try {
			$assess = ( new AssessDirWrite( $dir ) )->test();
			$canWrite = \count( \array_filter( $assess ) ) === 3;
		}
		catch ( \Exception $e ) {
			$canWrite = false;
		}
		return $canWrite;
	}

	public function writeRandomBytesFile( string $path, int $size ) :bool {
		return ( new DummyFile( $path, $size ) )->withRandomBytes( true );
	}
}

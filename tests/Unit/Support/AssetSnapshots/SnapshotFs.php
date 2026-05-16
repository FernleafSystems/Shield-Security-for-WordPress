<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots;

use FernleafSystems\Wordpress\Services\Core\Fs;

class SnapshotFs extends Fs {

	public function exists( $path ) :?bool {
		return \file_exists( $path );
	}

	public function mkdir( $path ) {
		return \is_dir( $path ) || @mkdir( $path, 0777, true );
	}

	public function getFileContent( $path, $uncompress = false ) {
		$contents = \file_get_contents( $path );
		if ( \is_string( $contents ) && $uncompress ) {
			$inflated = \gzinflate( $contents );
			return \is_string( $inflated ) ? $inflated : null;
		}
		return $contents;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		return \file_put_contents( $path, $compress ? \gzdeflate( $contents ) : $contents ) !== false;
	}

	public function deleteFile( $path ) {
		return !\is_file( $path ) || @unlink( $path );
	}

	public function getModifiedTime( string $path ) :int {
		return (int)\filemtime( $path );
	}

	public function touch( $path, $time = null ) {
		return \touch( $path, $time ?? \time() );
	}
}

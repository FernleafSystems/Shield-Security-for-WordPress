<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

class WorpdriveTestFilesystemHandle {

	public function is_readable( string $path ) :bool {
		return \is_readable( $path );
	}

	public function mtime( string $path ) :int {
		return (int)\filemtime( $path );
	}

	public function size( string $path ) :int {
		return (int)\filesize( $path );
	}
}

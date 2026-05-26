<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate;

class RelativeZipPathGuard {

	public function assertValid( string $path ) :void {
		$normal = \str_replace( '\\', '/', $path );

		if ( $path === ''
			 || \str_contains( $path, "\0" )
			 || \str_starts_with( $path, '/' )
			 || \str_starts_with( $path, '\\' )
			 || \preg_match( '#^[A-Za-z]:#', $path )
		) {
			throw new \InvalidArgumentException( 'Invalid WorpDrive zip path.' );
		}

		foreach ( \explode( '/', $normal ) as $segment ) {
			if ( $segment === '.' || $segment === '..' ) {
				throw new \InvalidArgumentException( 'Invalid WorpDrive zip path.' );
			}
		}
	}
}

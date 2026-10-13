<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Writes and removes the ruleset file. A new ruleset is written beside the current one, read back and checked,
 * then moved into place, so the request passes only ever load a complete file.
 */
class RulesetStore {

	public function write( string $path, array $ruleset ) :bool {
		$FS = Services::WpFs();
		$dir = \dirname( $path );
		if ( !$FS->isDir( $dir ) && !$FS->mkdir( $dir ) ) {
			return false;
		}

		$temp = $path.'.'.\str_replace( '.', '', \uniqid( '', true ) ).'.tmp';
		$written = $FS->putFileContent( $temp, RulesetFile::render( $ruleset ) )
				   && RulesetFile::read( $temp ) !== null
				   && $FS->move( $temp, $path );
		if ( !$written ) {
			$FS->deleteFile( $temp );
			return false;
		}
		$this->invalidateOpcache( $path );
		return true;
	}

	public function delete( string $path ) :bool {
		$FS = Services::WpFs();
		if ( !$FS->isFile( $path ) ) {
			return true;
		}
		$this->invalidateOpcache( $path );
		return (bool)$FS->deleteFile( $path );
	}

	/**
	 * The ruleset is loaded with include, so OPcache could otherwise keep serving the previous version.
	 */
	private function invalidateOpcache( string $path ) :void {
		if ( \function_exists( 'opcache_invalidate' ) ) {
			@\opcache_invalidate( $path, true );
		}
	}
}

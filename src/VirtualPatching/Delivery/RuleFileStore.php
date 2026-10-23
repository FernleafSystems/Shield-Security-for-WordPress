<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Verified rule files as delivered, one per plugin or theme: `{kind}-{slug}.json` holding the exact envelope bytes.
 * Each is written under a temporary name and moved into place, so a reader never sees part of one.
 */
class RuleFileStore {

	private string $dir;

	public function __construct( string $dir ) {
		$this->dir = \rtrim( $dir, '/\\' );
	}

	public function read( string $kind, string $slug ) :?string {
		$path = $this->path( $kind, $slug );
		if ( $path === null || !\is_file( $path ) || \is_link( $path ) ) {
			return null;
		}
		$bytes = @\file_get_contents( $path );
		return \is_string( $bytes ) ? $bytes : null;
	}

	public function write( string $kind, string $slug, string $bytes ) :bool {
		$path = $this->path( $kind, $slug );
		$FS = Services::WpFs();
		if ( $path === null || ( !$FS->isDir( $this->dir ) && !$FS->mkdir( $this->dir ) ) ) {
			return false;
		}
		$temp = $path.'.'.\str_replace( '.', '', \uniqid( '', true ) ).'.tmp';
		if ( !$FS->putFileContent( $temp, $bytes ) || @\file_get_contents( $temp ) !== $bytes || !$FS->move( $temp, $path ) ) {
			$FS->deleteFile( $temp );
			return false;
		}
		return true;
	}

	public function delete( string $kind, string $slug ) :void {
		$path = $this->path( $kind, $slug );
		if ( $path !== null && \is_file( $path ) ) {
			Services::WpFs()->deleteFile( $path );
		}
	}

	/**
	 * @return array<string, array{kind: string, slug: string}> every stored file's asset, by "kind/slug"
	 */
	public function assets() :array {
		if ( $this->dir === '' ) {
			return [];
		}
		$assets = [];
		foreach ( \glob( $this->dir.'/*.json' ) ?: [] as $path ) {
			if ( \is_file( $path ) && \preg_match( '#(?:^|[/\\\\])(plugin|theme)-([a-z0-9][a-z0-9_.-]{0,199})\.json$#D', $path, $match ) ) {
				$assets[ $match[ 1 ].'/'.$match[ 2 ] ] = [ 'kind' => $match[ 1 ], 'slug' => $match[ 2 ] ];
			}
		}
		\ksort( $assets );
		return $assets;
	}

	/**
	 * Without a directory, such as when Shield has no cache directory, nothing is stored.
	 */
	private function path( string $kind, string $slug ) :?string {
		return $this->dir !== '' && \in_array( $kind, [ 'plugin', 'theme' ], true )
			   && \preg_match( RuleFileVerifier::SLUG_PATTERN, $slug )
			? $this->dir.'/'.$kind.'-'.$slug.'.json' : null;
	}
}

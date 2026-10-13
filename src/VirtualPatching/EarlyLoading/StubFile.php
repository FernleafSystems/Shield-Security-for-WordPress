<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The stub on disk. PHP may be including it on every request, so it is replaced by renaming a complete
 * file over it, never written in place.
 */
class StubFile {

	private string $path;

	public function __construct( string $path ) {
		$this->path = $path;
	}

	public function exists() :bool {
		return \is_file( $this->path );
	}

	public function matches( string $content ) :bool {
		return $this->exists() && @\file_get_contents( $this->path ) === $content;
	}

	/**
	 * @throws InstallException
	 */
	public function write( string $content ) :void {
		if ( $this->matches( $content ) ) {
			return;
		}

		$temp = $this->path.'.'.\bin2hex( \random_bytes( 6 ) ).'.tmp';
		$written = @\file_put_contents( $temp, $content ) === \strlen( $content )
				   && @\file_get_contents( $temp ) === $content
				   && @\rename( $temp, $this->path );
		if ( !$written ) {
			@\unlink( $temp );
			throw new InstallException( InstallException::STUB_WRITE_FAILED, $this->path );
		}
		$this->invalidateOpcache();

		if ( !$this->matches( $content ) ) {
			throw new InstallException( InstallException::STUB_WRITE_FAILED, $this->path );
		}
	}

	public function delete() :bool {
		if ( !$this->exists() ) {
			return true;
		}
		$this->invalidateOpcache();
		return @\unlink( $this->path ) || !$this->exists();
	}

	/**
	 * WordPress's own helper checks that OPcache allows this before invalidating.
	 */
	private function invalidateOpcache() :void {
		if ( \function_exists( 'wp_opcache_invalidate' ) ) {
			\wp_opcache_invalidate( $this->path, true );
		}
		elseif ( \function_exists( 'opcache_invalidate' ) ) {
			@\opcache_invalidate( $this->path, true );
		}
	}
}

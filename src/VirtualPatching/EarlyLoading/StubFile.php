<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The stub on disk. PHP may be including it on every request, so new content is written to a candidate
 * file beside it, which can be requested to check it runs, and then renamed over the stub, never written in
 * place.
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
	 * Whether the file is a Shield stub, generated for this site or not.
	 */
	public function isShieldStub() :bool {
		$content = $this->exists() ? @\file_get_contents( $this->path, false, null, 0, 512 ) : false;
		return \is_string( $content ) && \strpos( $content, "<?php\n".StubBuilder::HEADER."\n" ) === 0;
	}

	/**
	 * @return string the candidate's path, a PHP file in the same directory
	 * @throws InstallException
	 */
	public function writeCandidate( string $content ) :string {
		$candidate = \dirname( $this->path ).'/'.\basename( $this->path, '.php' ).'-check-'.\bin2hex( \random_bytes( 6 ) ).'.php';
		if ( @\file_put_contents( $candidate, $content ) !== \strlen( $content ) || @\file_get_contents( $candidate ) !== $content ) {
			@\unlink( $candidate );
			throw new InstallException( InstallException::STUB_WRITE_FAILED, $this->path );
		}
		return $candidate;
	}

	/**
	 * @throws InstallException
	 */
	public function promote( string $candidate ) :void {
		$content = @\file_get_contents( $candidate );
		if ( !\is_string( $content ) || !@\rename( $candidate, $this->path ) ) {
			$this->discard( $candidate );
			throw new InstallException( InstallException::STUB_WRITE_FAILED, $this->path );
		}
		$this->invalidateOpcache( $this->path );
		if ( !$this->matches( $content ) ) {
			throw new InstallException( InstallException::STUB_WRITE_FAILED, $this->path );
		}
	}

	/**
	 * Removes candidates left behind by a request that stopped before finishing with them.
	 */
	public function removeStaleCandidates( int $now ) :void {
		$found = \function_exists( 'glob' ) ? \glob( \dirname( $this->path ).'/'.\basename( $this->path, '.php' ).'-check-*.php' ) : false;
		foreach ( \is_array( $found ) ? $found : [] as $candidate ) {
			$modified = @\filemtime( $candidate );
			if ( \is_int( $modified ) && $now - $modified > 600 ) {
				$this->discard( $candidate );
			}
		}
	}

	public function discard( string $candidate ) :void {
		$this->invalidateOpcache( $candidate );
		@\unlink( $candidate );
	}

	public function delete() :bool {
		if ( !$this->exists() ) {
			return true;
		}
		$this->invalidateOpcache( $this->path );
		return @\unlink( $this->path ) || !$this->exists();
	}

	/**
	 * WordPress's own helper checks that OPcache allows this before invalidating.
	 */
	private function invalidateOpcache( string $path ) :void {
		if ( \function_exists( 'wp_opcache_invalidate' ) ) {
			\wp_opcache_invalidate( $path, true );
		}
		elseif ( \function_exists( 'opcache_invalidate' ) ) {
			@\opcache_invalidate( $path, true );
		}
	}
}

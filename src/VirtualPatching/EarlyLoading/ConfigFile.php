<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Reads and rewrites a server configuration file in place under an exclusive lock, as WordPress's
 * insert_with_markers() does for .htaccess, so the file keeps its owner and permissions and
 * concurrent writers that also lock it cannot interleave.
 */
class ConfigFile {

	private string $path;

	public function __construct( string $path ) {
		$this->path = $path;
	}

	public function path() :string {
		return $this->path;
	}

	/**
	 * @throws ConfigFileException
	 */
	public function read() :?string {
		if ( !\file_exists( $this->path ) ) {
			return null;
		}
		$content = @\file_get_contents( $this->path );
		if ( !\is_string( $content ) ) {
			throw new ConfigFileException( sprintf( 'Could not read %s.', $this->path ) );
		}
		return $content;
	}

	/**
	 * Applies $change to the current content (null when the file does not exist) while holding the lock.
	 * $change returns the new content, or null to leave the file as it was. It may be called twice, so it
	 * must not have side effects. A missing file is only created when there is content to write.
	 * @param callable(?string):?string $change
	 * @return array{0:?string,1:?string} the content before and after
	 * @throws ConfigFileException
	 */
	public function modify( callable $change ) :array {
		$existed = \file_exists( $this->path );
		if ( $existed && ( !\is_file( $this->path ) || !\is_writable( $this->path ) ) ) {
			throw new ConfigFileException( sprintf( 'Could not write %s.', $this->path ) );
		}
		if ( !$existed && ( $change( null ) ?? '' ) === '' ) {
			return [ null, null ];
		}

		$handle = @\fopen( $this->path, 'c+' );
		if ( $handle === false ) {
			throw new ConfigFileException( sprintf( 'Could not open %s for writing.', $this->path ) );
		}

		$before = null;
		$after = null;
		$created = false;
		$completed = false;
		try {
			if ( !\flock( $handle, \LOCK_EX ) ) {
				throw new ConfigFileException( sprintf( 'Could not lock %s.', $this->path ) );
			}
			$read = \stream_get_contents( $handle );
			if ( !\is_string( $read ) ) {
				throw new ConfigFileException( sprintf( 'Could not read %s.', $this->path ) );
			}
			// Another writer may have created the file since the existence check.
			$created = !$existed && $read === '';
			$before = $created ? null : $read;

			$after = $change( $before );
			if ( $after !== null && $after !== $read ) {
				$this->replaceContent( $handle, $after );
			}
			$completed = true;
		}
		finally {
			\flock( $handle, \LOCK_UN );
			\fclose( $handle );
			if ( $created && ( !$completed || ( $after ?? '' ) === '' ) ) {
				@\unlink( $this->path );
			}
		}

		return [ $before, $after ?? $before ];
	}

	/**
	 * Puts the file back exactly as it was: the given content, or no file at all.
	 * @throws ConfigFileException
	 */
	public function restore( ?string $content ) :void {
		if ( $content === null ) {
			if ( \file_exists( $this->path ) && !@\unlink( $this->path ) ) {
				throw new ConfigFileException( sprintf( 'Could not remove %s.', $this->path ) );
			}
		}
		else {
			$this->modify( fn() => $content );
		}
	}

	/**
	 * @param resource $handle
	 * @throws ConfigFileException
	 */
	private function replaceContent( $handle, string $content ) :void {
		if ( !\ftruncate( $handle, 0 ) || !\rewind( $handle ) ) {
			throw new ConfigFileException( sprintf( 'Could not rewrite %s.', $this->path ) );
		}
		$written = 0;
		$length = \strlen( $content );
		while ( $written < $length ) {
			$bytes = \fwrite( $handle, \substr( $content, $written ) );
			if ( $bytes === false || $bytes === 0 ) {
				throw new ConfigFileException( sprintf( 'Could not write %s.', $this->path ) );
			}
			$written += $bytes;
		}
		\fflush( $handle );
	}
}

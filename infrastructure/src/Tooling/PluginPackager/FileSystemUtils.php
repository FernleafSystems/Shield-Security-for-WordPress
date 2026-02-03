<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Shared filesystem utilities for the plugin packager.
 */
class FileSystemUtils {

	/**
	 * Recursively remove a directory and all its contents
	 *
	 * @throws \RuntimeException if deletion fails
	 */
	public static function removeDirectoryRecursive( string $dir ) :void {
		if ( !is_dir( $dir ) ) {
			return;
		}

		// Read directory contents
		$files = @scandir( $dir );
		if ( $files === false ) {
			throw new \RuntimeException( sprintf( 'Failed to read directory contents: %s', $dir ) );
		}

		$files = array_diff( $files, [ '.', '..' ] );

		foreach ( $files as $file ) {
			$path = Path::join( $dir, $file );

			// Check if it's a symlink before processing
			if ( is_link( $path ) ) {
				// For symlinks, only delete the link itself, not the target
				if ( !@unlink( $path ) ) {
					throw new \RuntimeException(
						sprintf( 'Failed to delete symlink: %s', $path )
					);
				}
				continue;
			}

			if ( is_dir( $path ) ) {
				// Recursively delete subdirectories
				self::removeDirectoryRecursive( $path );
			}
			else {
				// Delete files - clear read-only attribute first (needed for .git files on Windows)
				@chmod( $path, 0666 );
				if ( !@unlink( $path ) ) {
					// Check if file still exists (might have been deleted by another process)
					if ( file_exists( $path ) ) {
						throw new \RuntimeException( sprintf( 'Failed to delete file: %s', $path ) );
					}
				}
			}
		}

		// Remove the directory itself - clear read-only attribute first
		@chmod( $dir, 0777 );
		if ( !@rmdir( $dir ) ) {
			// Check if directory still exists
			if ( is_dir( $dir ) ) {
				// Check if it's empty now (only . and ..)
				$finalCheck = @scandir( $dir );
				if ( $finalCheck !== false && \count( $finalCheck ) <= 2 ) {
					// Directory is actually empty, ignore the error
					return;
				}

				throw new \RuntimeException( sprintf( 'Failed to delete directory: %s', $dir ) );
			}
		}
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Handles cleanup operations after Strauss prefixing.
 * Removes duplicate libraries and cleans autoload files.
 */
class PostStraussCleanup {

	private SafeDirectoryRemover $directoryRemover;

	/** @var callable */
	private $logger;

	public function __construct( SafeDirectoryRemover $directoryRemover, callable $logger ) {
		$this->directoryRemover = $directoryRemover;
		$this->logger = $logger;
	}

	/**
	 * Clean up duplicate and development files from the package.
	 * Removes files that are no longer needed after Strauss prefixing.
	 */
	public function cleanPackageFiles( string $targetDir, ?string $straussForkRepo = null ) :void {
		$fs = new Filesystem();

		// Remove Strauss fork directory if it exists (only when not in Docker)
		// In Docker, we use /tmp which is ephemeral - no cleanup needed
		if ( $straussForkRepo !== null && !StraussBinaryProvider::isRunningInDocker() ) {
			$forkHash = \substr( \md5( $straussForkRepo ), 0, 12 );
			$forkDir = Path::join( $targetDir, '_strauss-fork-'.$forkHash );
			if ( is_dir( $forkDir ) ) {
				$this->log( 'Removing Strauss fork directory...' );
				try {
					$this->directoryRemover->removeSubdirectoryOf( $forkDir, $targetDir );
				}
				catch ( \Exception $e ) {
					// Non-critical cleanup - just warn
					$this->log( sprintf( '  Warning: %s', $e->getMessage() ) );
				}
			}
		}
		elseif ( $straussForkRepo !== null && StraussBinaryProvider::isRunningInDocker() ) {
			$this->log( 'Skipping Strauss fork cleanup (Docker /tmp is ephemeral)' );
		}

		// Directories to remove (duplicates after Strauss prefixing)
		$this->log( 'Removing duplicate libraries from main vendor...' );
		$directoriesToRemove = [
			Path::join( $targetDir, 'vendor', 'twig' ),
			Path::join( $targetDir, 'vendor', 'monolog' ),
			Path::join( $targetDir, 'vendor', 'bin' ),
		];

		foreach ( $directoriesToRemove as $dir ) {
			if ( is_dir( $dir ) ) {
				try {
					$this->directoryRemover->removeSubdirectoryOf( $dir, $targetDir );
				}
				catch ( \Exception $e ) {
					// Log but don't fail - these are cleanup operations
					$this->log( sprintf( '  Warning: Could not remove directory: %s (%s)', $dir, $e->getMessage() ) );
				}
			}
		}

		// Files to remove (development and temporary files)
		$this->log( 'Removing development-only files...' );
		$filesToRemove = [
			Path::join( $targetDir, 'vendor_prefixed', 'autoload-files.php' ),
			Path::join( $targetDir, 'strauss.phar' ),
		];

		foreach ( $filesToRemove as $file ) {
			if ( file_exists( $file ) ) {
				try {
					$fs->remove( $file );
				}
				catch ( \Exception $e ) {
					// Log but don't fail - these are cleanup operations
					$this->log( sprintf( '  Warning: Could not remove file: %s (%s)', $file, $e->getMessage() ) );
				}
			}
		}
	}

	/**
	 * Clean autoload files to remove twig references.
	 * Preserves original line endings (CRLF/LF).
	 */
	public function cleanAutoloadFiles( string $targetDir ) :void {
		$this->log( 'Cleaning autoload files...' );

		$composerDir = Path::join( $targetDir, 'vendor', 'composer' );

		if ( !is_dir( $composerDir ) ) {
			$this->log( '  Warning: Composer directory not found, skipping autoload cleanup' );
			return;
		}

		// Files to clean
		$filesToClean = [
			'autoload_files.php',
			'autoload_static.php',
			'autoload_psr4.php',
		];

		foreach ( $filesToClean as $filename ) {
			$filePath = Path::join( $composerDir, $filename );

			if ( !file_exists( $filePath ) ) {
				continue;
			}

			$this->log( sprintf( 'Cleaning %s...', $filename ) );

			$content = file_get_contents( $filePath );
			if ( $content === false ) {
				$this->log( sprintf( '  Warning: Could not read %s', $filename ) );
				continue;
			}

			// Detect line ending style (CRLF or LF)
			$lineEnding = strpos( $content, "\r\n" ) !== false ? "\r\n" : "\n";

			// Split into lines, preserving the line ending detection
			$lines = explode( $lineEnding, $content );

			// Count twig references before cleaning
			$twigCountBefore = 0;
			foreach ( $lines as $line ) {
				if ( strpos( $line, '/twig/twig/' ) !== false ) {
					$twigCountBefore++;
				}
			}
			$this->log( sprintf( '  - Found %d twig references', $twigCountBefore ) );

			// Filter out lines containing /twig/twig/
			$filteredLines = array_filter( $lines, static function ( string $line ) :bool {
				return strpos( $line, '/twig/twig/' ) === false;
			} );

			// Re-index array and join back with original line ending
			$newContent = implode( $lineEnding, array_values( $filteredLines ) );

			// Write back to file
			$bytesWritten = file_put_contents( $filePath, $newContent );
			if ( $bytesWritten === false ) {
				$this->log( sprintf( '  Warning: Could not write %s', $filename ) );
				continue;
			}

			// Count twig references after cleaning
			$twigCountAfter = 0;
			foreach ( $filteredLines as $line ) {
				if ( strpos( $line, '/twig/twig/' ) !== false ) {
					$twigCountAfter++;
				}
			}
			$this->log( sprintf( '  - After cleaning: %d twig references', $twigCountAfter ) );
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}

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
	 *
	 * @param string[] $prefixedPackages
	 */
	public function cleanPackageFiles( string $targetDir, ?string $straussForkRepo = null, array $prefixedPackages = [] ) :void {
		$fs = new Filesystem();

		// Remove Strauss fork directory if it exists (only when not in Docker)
		// In Docker, we use /tmp which is ephemeral - no cleanup needed
		if ( $straussForkRepo !== null && !StraussBinaryProvider::isRunningInDocker() ) {
			$this->removeStraussForkDirectories( $targetDir );
		}
		elseif ( $straussForkRepo !== null && StraussBinaryProvider::isRunningInDocker() ) {
			$this->log( 'Skipping Strauss fork cleanup (Docker /tmp is ephemeral)' );
		}

		// Remove duplicate vendor libraries after Strauss prefixing.
		$this->log( 'Removing duplicate libraries from main vendor...' );
		$directoriesToRemove = [ Path::join( $targetDir, 'vendor', 'bin' ) ];
		$namespaceDirs = [];
		foreach ( $prefixedPackages as $package ) {
			$parts = \explode( '/', $package );
			if ( \count( $parts ) !== 2 || $parts[ 0 ] === '' || $parts[ 1 ] === '' ) {
				continue;
			}

			$namespaceDir = Path::join( $targetDir, 'vendor', $parts[ 0 ] );
			$namespaceDirs[ $namespaceDir ] = $namespaceDir;
			$directoriesToRemove[] = Path::join( $namespaceDir, $parts[ 1 ] );
		}

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

		foreach ( $namespaceDirs as $namespaceDir ) {
			if ( \is_dir( $namespaceDir ) && $this->directoryIsEmpty( $namespaceDir ) ) {
				try {
					$this->directoryRemover->removeSubdirectoryOf( $namespaceDir, $targetDir );
				}
				catch ( \Exception $e ) {
					$this->log( sprintf( '  Warning: Could not remove directory: %s (%s)', $namespaceDir, $e->getMessage() ) );
				}
			}
		}

		$packagesDir = Path::join( $targetDir, 'packages' );
		if ( \is_dir( $packagesDir ) ) {
			$entries = \scandir( $packagesDir ) ?: [];
			$entries = \array_values( \array_diff( $entries, [ '.', '..' ] ) );
			if ( $entries === [] ) {
				try {
					$this->directoryRemover->removeSubdirectoryOf( $packagesDir, $targetDir );
				}
				catch ( \Exception $e ) {
					$this->log( sprintf( '  Warning: Could not remove directory: %s (%s)', $packagesDir, $e->getMessage() ) );
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

	private function removeStraussForkDirectories( string $targetDir ) :void {
		$entries = \scandir( $targetDir ) ?: [];
		foreach ( $entries as $entry ) {
			if ( \strpos( $entry, '_strauss-fork-' ) !== 0 ) {
				continue;
			}

			$forkDir = Path::join( $targetDir, $entry );
			if ( !\is_dir( $forkDir ) ) {
				continue;
			}

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

	/**
	 * Clean autoload files to remove references to packages now loaded from vendor_prefixed.
	 * Preserves original line endings (CRLF/LF).
	 *
	 * @param string[] $prefixedPackages
	 */
	public function cleanAutoloadFiles( string $targetDir, array $prefixedPackages = [] ) :void {
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

			// Count prefixed package references before cleaning
			$packageCountBefore = 0;
			foreach ( $lines as $line ) {
				if ( $this->containsPrefixedPackagePath( $line, $prefixedPackages ) ) {
					$packageCountBefore++;
				}
			}
			$this->log( sprintf( '  - Found %d prefixed package references', $packageCountBefore ) );

			// Filter out lines containing prefixed package paths.
			$filteredLines = array_filter(
				$lines,
				fn( string $line ) :bool => !$this->containsPrefixedPackagePath( $line, $prefixedPackages )
			);

			// Re-index array and join back with original line ending
			$newContent = implode( $lineEnding, array_values( $filteredLines ) );

			// Write back to file
			$bytesWritten = file_put_contents( $filePath, $newContent );
			if ( $bytesWritten === false ) {
				$this->log( sprintf( '  Warning: Could not write %s', $filename ) );
				continue;
			}

			// Count prefixed package references after cleaning
			$packageCountAfter = 0;
			foreach ( $filteredLines as $line ) {
				if ( $this->containsPrefixedPackagePath( $line, $prefixedPackages ) ) {
					$packageCountAfter++;
				}
			}
			$this->log( sprintf( '  - After cleaning: %d prefixed package references', $packageCountAfter ) );
		}
	}

	/**
	 * @param string[] $prefixedPackages
	 */
	private function containsPrefixedPackagePath( string $line, array $prefixedPackages ) :bool {
		foreach ( $prefixedPackages as $package ) {
			if ( \strpos( $line, '/'.$package.'/' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function directoryIsEmpty( string $dir ) :bool {
		$entries = \scandir( $dir );
		return $entries !== false && \array_values( \array_diff( $entries, [ '.', '..' ] ) ) === [];
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}

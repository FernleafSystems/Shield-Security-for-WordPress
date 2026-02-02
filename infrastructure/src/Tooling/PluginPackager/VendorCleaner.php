<?php
declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Cleans development files from vendor directories.
 *
 * Removes test directories, documentation, CI configs, etc. from DIRECT CHILDREN
 * of each package directory. Applies to both vendor and vendor_prefixed directories.
 */
class VendorCleaner {

	/**
	 * Patterns for cleaning development files from vendor directories.
	 * Only applied to DIRECT CHILDREN of each package directory.
	 */
	private const CLEANUP_PATTERNS = [
		// Directories to remove entirely (lowercase - matched case-insensitively)
		'directories' => [
			'tests',
			'test',
			'.github',
			'.githooks',
			'doc',
			'docs',
			'example',
			'examples',
			'bin',
		],
		// Files to remove (glob patterns, lowercase - matched case-insensitively)
		'files'       => [
			'.gitignore*',
			'readme*',
			'changelog*',
			'changes*',
			'upgrade*',
			'contributing*',
			'code_of_conduct*',
			'security*',
			'license*',
			'.gitignore',
			'.gitattributes',
			'.editorconfig',
			'.php-cs-fixer*',
			'.php_cs*',
			'phpunit.xml*',
			'phpstan.neon*',
			'psalm.xml*',
			'.travis.yml',
			'.gitlab-ci.yml',
			'codecov.yml',
			'makefile',
			'*.sh',
			'*.bat',
			'composer.json',
			'composer.lock',
			'.styleci.yml',
			'phpbench.json',
			'infection.json*',
			'phpcs.xml*',
			'humbug.json*',
			'box.json*',
		],
	];

	/**
	 * Package-specific paths for cleanup.
	 * Maps a path (file or directory) to the list of packages where it should be removed.
	 * Supports subdirectories (e.g., 'tools/tests').
	 * Path matching is case-insensitive.
	 * Package format: "vendor-name/package-name" (lowercase)
	 */
	private const PACKAGE_PATTERNS = [
		'.githooks' => [
			'crowdsec/capi-client',
			'crowdsec/common',
		],
		'tools' => [
			'crowdsec/capi-client',
			'crowdsec/common',
		],
	];

	/** @var callable */
	private $logger;

	public function __construct( ?callable $logger = null ) {
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * Clean development files from vendor directories.
	 *
	 * @param string $targetDir The package target directory
	 * @return array{dirs: int, files: int} Cleanup statistics
	 */
	public function clean( string $targetDir ) :array {
		$this->log( 'Cleaning vendor development files...' );

		$libDir = Path::join( $targetDir, 'src', 'lib' );
		$vendorDirs = [
			Path::join( $libDir, 'vendor' ),
			Path::join( $libDir, 'vendor_prefixed' ),
		];

		$stats = [ 'dirs' => 0, 'files' => 0 ];

		foreach ( $vendorDirs as $vendorDir ) {
			if ( !is_dir( $vendorDir ) ) {
				continue;
			}

			$this->log( sprintf( '  Processing: %s', basename( $vendorDir ) ) );
			$this->cleanVendorPackages( $vendorDir, $stats );
		}

		$this->log( sprintf( '  Removed %d directories and %d files', $stats[ 'dirs' ], $stats[ 'files' ] ) );

		return $stats;
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	/**
	 * Clean development files from a single vendor directory (vendor or vendor_prefixed).
	 * Structure: vendor/{vendor-name}/{package-name}/{target-to-remove}
	 *
	 * @param array<string,int> $stats Reference to stats array for counting
	 */
	private function cleanVendorPackages( string $vendorDir, array &$stats ) :void {
		$fs = new Filesystem();
		$globalDirPatterns = self::CLEANUP_PATTERNS[ 'directories' ];
		$globalFilePatterns = self::CLEANUP_PATTERNS[ 'files' ];

		// Build reverse lookup: package -> paths to delete
		$packagePaths = $this->buildPackagePathsLookup();

		// Iterate vendor/{vendor-name} directories
		$vendorNameDirs = @scandir( $vendorDir );
		if ( $vendorNameDirs === false ) {
			return;
		}

		foreach ( $vendorNameDirs as $vendorName ) {
			if ( $vendorName === '.' || $vendorName === '..' ) {
				continue;
			}

			$vendorNamePath = Path::join( $vendorDir, $vendorName );
			if ( !is_dir( $vendorNamePath ) ) {
				continue;
			}

			// Iterate vendor/{vendor-name}/{package-name} directories
			$packageDirs = @scandir( $vendorNamePath );
			if ( $packageDirs === false ) {
				continue;
			}

			foreach ( $packageDirs as $packageName ) {
				if ( $packageName === '.' || $packageName === '..' ) {
					continue;
				}

				$packagePath = Path::join( $vendorNamePath, $packageName );
				if ( !is_dir( $packagePath ) ) {
					continue;
				}

				// Build package key: vendor-name/package-name (lowercase)
				$packageKey = \strtolower( $vendorName.'/'.$packageName );

				// Clean direct children with global patterns
				$this->cleanPackageDirectChildren( $packagePath, $globalDirPatterns, $globalFilePatterns, $stats, $fs );

				// Clean package-specific paths (supports subdirectories)
				if ( isset( $packagePaths[ $packageKey ] ) ) {
					foreach ( $packagePaths[ $packageKey ] as $relativePath ) {
						$this->cleanPackagePath( $packagePath, $relativePath, $stats, $fs );
					}
				}
			}
		}
	}

	/**
	 * Build reverse lookup from PACKAGE_PATTERNS: package -> list of paths to delete.
	 *
	 * @return array<string, string[]>
	 */
	private function buildPackagePathsLookup() :array {
		$result = [];
		foreach ( self::PACKAGE_PATTERNS as $path => $packages ) {
			foreach ( $packages as $package ) {
				$packageKey = \strtolower( $package );
				if ( !isset( $result[ $packageKey ] ) ) {
					$result[ $packageKey ] = [];
				}
				$result[ $packageKey ][] = $path;
			}
		}
		return $result;
	}

	/**
	 * Clean a specific path within a package directory.
	 * Supports subdirectories (e.g., 'tools/tests').
	 * Path matching is case-insensitive.
	 *
	 * @param array<string,int> $stats Reference to stats array
	 */
	private function cleanPackagePath( string $packagePath, string $relativePath, array &$stats, Filesystem $fs ) :void {
		// Normalize path separators
		$relativePath = \str_replace( '\\', '/', $relativePath );

		// Resolve the path case-insensitively
		$targetPath = $this->resolvePathCaseInsensitive( $packagePath, $relativePath );
		if ( $targetPath === null ) {
			return; // Path doesn't exist
		}

		if ( is_dir( $targetPath ) ) {
			try {
				FileSystemUtils::removeDirectoryRecursive( $targetPath );
				$stats[ 'dirs' ]++;
			}
			catch ( \Exception $e ) {
				// Log but don't fail
			}
		}
		elseif ( is_file( $targetPath ) ) {
			try {
				$fs->remove( $targetPath );
				$stats[ 'files' ]++;
			}
			catch ( \Exception $e ) {
				// Ignore
			}
		}
	}

	/**
	 * Resolve a relative path case-insensitively within a base directory.
	 * Returns the actual filesystem path if found, null otherwise.
	 */
	private function resolvePathCaseInsensitive( string $basePath, string $relativePath ) :?string {
		$segments = \explode( '/', $relativePath );
		$currentPath = $basePath;

		foreach ( $segments as $segment ) {
			if ( $segment === '' ) {
				continue;
			}

			$found = false;
			$entries = @scandir( $currentPath );
			if ( $entries === false ) {
				return null;
			}

			foreach ( $entries as $entry ) {
				if ( $entry === '.' || $entry === '..' ) {
					continue;
				}
				if ( \strtolower( $entry ) === \strtolower( $segment ) ) {
					$currentPath = Path::join( $currentPath, $entry );
					$found = true;
					break;
				}
			}

			if ( !$found ) {
				return null;
			}
		}

		return $currentPath;
	}

	/**
	 * Clean direct children of a single package directory.
	 *
	 * @param string[]          $dirPatterns  Directory names to remove
	 * @param string[]          $filePatterns File glob patterns to remove
	 * @param array<string,int> $stats        Reference to stats array
	 */
	private function cleanPackageDirectChildren(
		string $packagePath,
		array $dirPatterns,
		array $filePatterns,
		array &$stats,
		Filesystem $fs
	) :void {
		$children = @scandir( $packagePath );
		if ( $children === false ) {
			return;
		}

		foreach ( $children as $child ) {
			if ( $child === '.' || $child === '..' ) {
				continue;
			}

			$childPath = Path::join( $packagePath, $child );

			// Check if it's a directory to remove (case-insensitive)
			if ( is_dir( $childPath ) && \in_array( \strtolower( $child ), $dirPatterns, true ) ) {
				try {
					FileSystemUtils::removeDirectoryRecursive( $childPath );
					$stats[ 'dirs' ]++;
				}
				catch ( \Exception $e ) {
					// Log but don't fail
				}
				continue;
			}

			// Check if it's a file to remove
			if ( is_file( $childPath ) ) {
				foreach ( $filePatterns as $pattern ) {
					if ( fnmatch( $pattern, \strtolower( $child ) ) ) {
						try {
							$fs->remove( $childPath );
							$stats[ 'files' ]++;
						}
						catch ( \Exception $e ) {
							// Ignore
						}
						break;
					}
				}
			}
		}
	}
}

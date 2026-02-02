<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Copies plugin files to a target directory, respecting .gitattributes export-ignore patterns.
 */
class PluginFileCopier {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( string $projectRoot, ?callable $logger = null ) {
		$this->projectRoot = $projectRoot;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * Copy plugin files to the target package directory.
	 * Uses .gitattributes export-ignore patterns as single source of truth.
	 *
	 * @return array{files: int, dirs: int} Statistics about copied items
	 * @throws \RuntimeException|\Symfony\Component\Filesystem\Exception\InvalidArgumentException if copy operations fail
	 */
	public function copy( string $targetDir ) :array {
		$this->log( 'Copying plugin files...' );
		$this->log( '(Using .gitattributes export-ignore as source of truth)' );

		$fs = new Filesystem();
		$excludePatterns = $this->getExportIgnorePatterns();

		$this->log( sprintf( '  Loaded %d export-ignore patterns from .gitattributes', count( $excludePatterns ) ) );

		// Ensure target directory exists
		if ( !is_dir( $targetDir ) ) {
			$fs->mkdir( $targetDir );
		}

		// Use filter iterator to avoid descending into excluded directories
		// This is much faster than iterating all files then skipping
		// Note: Since PHP 5.4, closures auto-bind $this when defined in class methods
		//
		// Symlink behavior: RecursiveDirectoryIterator follows symlinks by default.
		// If a symlink points outside the project, Path::makeRelative() may return
		// unexpected results. This is acceptable as symlinks in the source tree are rare.
		$projectRoot = $this->projectRoot;

		// Check if target directory is inside project root to prevent infinite recursion (required for CI)
		$isTargetInsideRoot = Path::isBasePath( $projectRoot, $targetDir );
		if ( $isTargetInsideRoot ) {
			$this->log( sprintf( '  Excluding target directory from copy: %s', Path::makeRelative( $targetDir, $projectRoot ) ) );
		}

		$dirIterator = new \RecursiveDirectoryIterator(
			$this->projectRoot,
			\FilesystemIterator::SKIP_DOTS
		);

		// Filter out excluded directories BEFORE descending into them
		$filterIterator = new \RecursiveCallbackFilterIterator(
			$dirIterator,
			function ( \SplFileInfo $current, string $key, \RecursiveDirectoryIterator $iterator ) use ( $projectRoot, $excludePatterns, $targetDir, $isTargetInsideRoot ) :bool {
				$relativePath = Path::makeRelative( $current->getPathname(), $projectRoot );

				// Skip the target directory itself to prevent infinite recursion
				if ( $isTargetInsideRoot && Path::isBasePath( $targetDir, $current->getPathname() ) ) {
					return false;
				}

				// Skip export-ignore paths
				if ( $this->shouldExcludePath( $relativePath, $excludePatterns ) ) {
					return false;
				}

				return true;
			}
		);

		$iterator = new \RecursiveIteratorIterator(
			$filterIterator,
			\RecursiveIteratorIterator::SELF_FIRST
		);

		$stats = [ 'files' => 0, 'dirs' => 0 ];

		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$fullPath = $item->getPathname();
			$relativePath = Path::makeRelative( $fullPath, $this->projectRoot );

			$targetPath = Path::join( $targetDir, $relativePath );

			if ( $item->isDir() ) {
				if ( !is_dir( $targetPath ) ) {
					$fs->mkdir( $targetPath );
					$stats[ 'dirs' ]++;
				}
			}
			else {
				try {
					$fs->copy( $fullPath, $targetPath, true );
					$stats[ 'files' ]++;
				}
				catch ( \Exception $e ) {
					throw new \RuntimeException(
						sprintf( 'Failed to copy file "%s": %s', $relativePath, $e->getMessage() )
					);
				}
			}
		}

		$this->log( sprintf( '  ✓ Copied %d files in %d directories', $stats[ 'files' ], $stats[ 'dirs' ] ) );
		$this->log( '  (Excluded paths filtered via .gitattributes export-ignore)' );
		$this->log( '' );
		$this->log( 'Package structure created successfully' );
		$this->log( '' );

		return $stats;
	}

	/**
	 * Parse .gitattributes and return list of export-ignore patterns.
	 * This makes .gitattributes the single source of truth for package contents.
	 *
	 * @return string[] Array of patterns that should be excluded from packages
	 */
	public function getExportIgnorePatterns() :array {
		$gitattributesPath = Path::join( $this->projectRoot, '.gitattributes' );

		if ( !file_exists( $gitattributesPath ) ) {
			$this->log( 'Warning: .gitattributes not found, no export-ignore patterns loaded' );
			return [];
		}

		$patterns = [];
		$lines = file( $gitattributesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( $lines === false ) {
			$this->log( 'Warning: Could not read .gitattributes' );
			return [];
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip comments and empty lines
			if ( $line === '' || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			// Match lines with export-ignore attribute
			// Formats supported:
			//   /path export-ignore
			//   /path/to/dir export-ignore
			//   /languages/*.po export-ignore
			if ( preg_match( '/^(\S+)\s+.*\bexport-ignore\b/', $line, $matches ) ) {
				$pattern = $matches[ 1 ];
				// Normalize: remove leading slash for internal consistency
				$patterns[] = ltrim( $pattern, '/' );
			}
		}

		return $patterns;
	}

	/**
	 * Check if a path should be excluded based on export-ignore patterns.
	 * Supports:
	 *   - Exact file matches: "README.md"
	 *   - Directory matches: "tests" matches "tests/Unit/Test.php"
	 *   - Glob patterns: "languages/*.po" matches "languages/en_US.po"
	 *
	 * NOTE: Matching is case-sensitive (consistent with git's behavior).
	 * Git tracks paths exactly as committed, so case mismatches are rare.
	 *
	 * @param string   $relativePath Path relative to project root (forward slashes)
	 * @param string[] $patterns     Export-ignore patterns from .gitattributes
	 * @return bool True if path should be excluded
	 */
	public function shouldExcludePath( string $relativePath, array $patterns ) :bool {
		// Normalize to forward slashes (simpler than Path::normalize() for this use case)
		// We only need to handle backslash->forward slash conversion, not ../ resolution
		$normalizedPath = str_replace( '\\', '/', $relativePath );

		foreach ( $patterns as $pattern ) {
			// Normalize pattern
			$normalizedPattern = str_replace( '\\', '/', $pattern );

			// Case 1: Exact match
			if ( $normalizedPath === $normalizedPattern ) {
				return true;
			}

			// Case 2: Directory match - pattern is a directory prefix
			// Pattern "tests" should match "tests/Unit/Test.php"
			$patternAsDir = rtrim( $normalizedPattern, '/' ).'/';
			if ( strpos( $normalizedPath, $patternAsDir ) === 0 ) {
				return true;
			}

			// Case 3: Pattern ends with directory name, path is exactly that directory
			if ( $normalizedPath === rtrim( $normalizedPattern, '/' ) ) {
				return true;
			}

			// Case 4: Glob pattern with asterisk (e.g., "languages/*.po")
			if ( strpos( $normalizedPattern, '*' ) !== false ) {
				// Convert glob pattern to regex
				// Escape regex special chars except *
				$regexPattern = preg_quote( $normalizedPattern, '#' );
				// Replace escaped \* with regex .*
				$regexPattern = str_replace( '\\*', '[^/]*', $regexPattern );
				// Anchor the pattern
				$regexPattern = '#^'.$regexPattern.'$#';

				if ( preg_match( $regexPattern, $normalizedPath ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}

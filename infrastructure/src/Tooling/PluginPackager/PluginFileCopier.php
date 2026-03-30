<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Copies plugin files to a target directory, respecting intentional export-ignore
 * rules and filtering ignored local artifacts that should not ship in a package.
 */
class PluginFileCopier {

	private const GIT_IGNORED_PATHS_COMMAND = [
		'git',
		'ls-files',
		'--others',
		'--ignored',
		'--exclude-standard',
		'--directory',
		'-z',
	];

	private const IGNORED_PATH_ALLOWLIST = [
		'assets/dist',
	];

	private string $projectRoot;

	/** @var callable */
	private $logger;

	private ProcessRunner $processRunner;

	public function __construct( string $projectRoot, callable $logger, ?ProcessRunner $processRunner = null ) {
		$this->projectRoot = $projectRoot;
		$this->logger = $logger;
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * Copy plugin files to the target package directory.
	 *
	 * @return array{files: int, dirs: int} Statistics about copied items
	 * @throws \RuntimeException|\Symfony\Component\Filesystem\Exception\InvalidArgumentException if copy operations fail
	 */
	public function copy( string $targetDir ) :array {
		$this->log( 'Copying plugin files...' );
		$this->log( '(Applying .gitattributes export-ignore rules and filtering ignored local artifacts)' );

		$fs = new Filesystem();
		$excludePatterns = $this->getExportIgnorePatterns();
		$ignoredLocalPathsState = $this->getIgnoredLocalPaths();
		$ignoredLocalPaths = $ignoredLocalPathsState[ 'paths' ];

		$this->log( sprintf( '  Loaded %d export-ignore patterns from .gitattributes', count( $excludePatterns ) ) );
		$this->log( sprintf(
			$ignoredLocalPathsState[ 'loaded_from_git' ]
				? '  Loaded %d ignored local paths from git'
				: '  Ignored local path discovery unavailable; using 0 git-reported ignored paths',
			count( $ignoredLocalPaths )
		) );

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
			function ( \SplFileInfo $current ) use ( $projectRoot, $excludePatterns, $ignoredLocalPaths, $targetDir, $isTargetInsideRoot ) :bool {
				$relativePath = Path::makeRelative( $current->getPathname(), $projectRoot );

				// Skip the target directory itself to prevent infinite recursion
				if ( $isTargetInsideRoot && Path::isBasePath( $targetDir, $current->getPathname() ) ) {
					return false;
				}

				if ( $this->shouldExcludePath( $relativePath, $excludePatterns ) ) {
					return false;
				}

				if ( $this->shouldExcludeIgnoredLocalPath( $relativePath, $ignoredLocalPaths ) ) {
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

		$this->log( sprintf( '  OK Copied %d files in %d directories', $stats[ 'files' ], $stats[ 'dirs' ] ) );
		$this->log( '  (Excluded paths filtered via .gitattributes export-ignore and ignored local artifact checks)' );
		$this->log( '' );
		$this->log( 'Package structure created successfully' );
		$this->log( '' );

		return $stats;
	}

	/**
	 * Parse .gitattributes and return list of export-ignore patterns.
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

			if ( $line === '' || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			if ( preg_match( '/^(\S+)\s+.*\bexport-ignore\b/', $line, $matches ) ) {
				$patterns[] = $this->normalizeRelativePath( $matches[ 1 ] );
			}
		}

		return $patterns;
	}

	/**
	 * Check if a path should be excluded based on export-ignore patterns.
	 *
	 * @param string   $relativePath Path relative to project root (forward slashes)
	 * @param string[] $patterns     Export-ignore patterns from .gitattributes
	 */
	public function shouldExcludePath( string $relativePath, array $patterns ) :bool {
		$normalizedPath = $this->normalizeRelativePath( $relativePath );

		foreach ( $patterns as $pattern ) {
			$normalizedPattern = $this->normalizeRelativePath( $pattern );

			if ( $this->pathMatchesSelfOrDescendant( $normalizedPath, $normalizedPattern ) ) {
				return true;
			}

			if ( strpos( $normalizedPattern, '*' ) !== false ) {
				$regexPattern = preg_quote( $normalizedPattern, '#' );
				$regexPattern = str_replace( '\\*', '[^/]*', $regexPattern );
				$regexPattern = '#^'.$regexPattern.'$#';

				if ( preg_match( $regexPattern, $normalizedPath ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return array{paths: string[], loaded_from_git: bool}
	 */
	private function getIgnoredLocalPaths() :array {
		$gitDir = Path::join( $this->projectRoot, '.git' );
		if ( !file_exists( $gitDir ) ) {
			$this->log( 'Warning: .git not found, skipping ignored local artifact filtering' );
			return [
				'paths' => [],
				'loaded_from_git' => false,
			];
		}

		try {
			$process = $this->processRunner->run(
				self::GIT_IGNORED_PATHS_COMMAND,
				$this->projectRoot,
				static function () :void {
				}
			);
		}
		catch ( \Throwable $e ) {
			$this->log( sprintf( 'Warning: Failed to inspect ignored local artifacts: %s', $e->getMessage() ) );
			return [
				'paths' => [],
				'loaded_from_git' => false,
			];
		}

		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			$errorOutput = trim( $process->getErrorOutput() );
			$this->log( sprintf(
				'Warning: git ignored-path inspection failed with exit code %d%s',
				$exitCode,
				$errorOutput !== '' ? sprintf( ' (%s)', $errorOutput ) : ''
			) );
			return [
				'paths' => [],
				'loaded_from_git' => false,
			];
		}

		$stdout = $process->getOutput();
		if ( $stdout === '' ) {
			return [
				'paths' => [],
				'loaded_from_git' => true,
			];
		}

		$paths = array_values( array_filter( array_map(
			fn( string $path ) :string => $this->normalizeRelativePath( $path ),
			explode( "\0", $stdout )
		) ) );

		$paths = array_values( array_filter(
			$paths,
			fn( string $path ) :bool => !$this->isIgnoredPathAllowlisted( $path )
		) );

		$paths = array_values( array_unique( $paths ) );
		sort( $paths );

		return [
			'paths' => $paths,
			'loaded_from_git' => true,
		];
	}

	/**
	 * @param string[] $ignoredLocalPaths
	 */
	private function shouldExcludeIgnoredLocalPath( string $relativePath, array $ignoredLocalPaths ) :bool {
		$normalizedPath = $this->normalizeRelativePath( $relativePath );
		if ( $normalizedPath === '' || $this->isIgnoredPathAllowlisted( $normalizedPath ) ) {
			return false;
		}

		foreach ( $ignoredLocalPaths as $ignoredPath ) {
			$normalizedIgnoredPath = $this->normalizeRelativePath( $ignoredPath );
			if ( $normalizedIgnoredPath === '' ) {
				continue;
			}

			if ( $this->pathMatchesSelfOrDescendant( $normalizedPath, $normalizedIgnoredPath ) ) {
				return true;
			}
		}

		return false;
	}

	private function isIgnoredPathAllowlisted( string $relativePath ) :bool {
		$normalizedPath = $this->normalizeRelativePath( $relativePath );

		foreach ( self::IGNORED_PATH_ALLOWLIST as $allowlistedPath ) {
			$normalizedAllowlistedPath = $this->normalizeRelativePath( $allowlistedPath );

			if ( $this->pathMatchesSelfOrDescendant( $normalizedPath, $normalizedAllowlistedPath ) ) {
				return true;
			}
		}

		return false;
	}

	private function normalizeRelativePath( string $path ) :string {
		$normalized = trim( str_replace( '\\', '/', $path ) );

		while ( strpos( $normalized, './' ) === 0 ) {
			$normalized = substr( $normalized, 2 );
		}

		return trim( $normalized, '/' );
	}

	private function pathMatchesSelfOrDescendant( string $path, string $candidate ) :bool {
		$normalizedPath = rtrim( $path, '/' );
		$normalizedCandidate = rtrim( $candidate, '/' );

		return $normalizedPath === $normalizedCandidate
			|| strpos( $normalizedPath, $normalizedCandidate.'/' ) === 0;
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}

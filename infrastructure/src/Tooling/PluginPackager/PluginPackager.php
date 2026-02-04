<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use FernleafSystems\ShieldPlatform\Tooling\ConfigMerger;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Orchestrates plugin packaging by coordinating specialized components.
 */
class PluginPackager {

	private string $projectRoot;

	private string $straussVersion = '';

	private ?string $straussForkRepo = null;

	/** @var callable */
	private $logger;

	private CommandRunner $commandRunner;

	private SafeDirectoryRemover $directoryRemover;

	private PluginFileCopier $fileCopier;

	private VersionUpdater $versionUpdater;

	public function __construct( ?string $projectRoot = null, ?callable $logger = null ) {
		$root = $projectRoot ?? $this->detectProjectRoot();
		if ( $root === '' ) {
			throw new \RuntimeException( 'Unable to determine project root.' );
		}
		$this->projectRoot = $root;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};

		// Initialize extracted dependencies
		$this->commandRunner = new CommandRunner( $this->projectRoot, $this->logger );
		$this->directoryRemover = new SafeDirectoryRemover( $this->projectRoot );
		$this->fileCopier = new PluginFileCopier( $this->projectRoot, $this->logger );
		$this->versionUpdater = new VersionUpdater( $this->projectRoot, $this->logger );
	}

	/**
	 * @param array<string,bool> $options
	 * @throws InvalidArgumentException
	 */
	public function package( ?string $outputDir = null, array $options = [] ) :string {
		$options = $this->resolveOptions( $options );
		$this->straussVersion = $this->resolveStraussVersion( $options );
		$this->straussForkRepo = $options[ 'strauss_fork_repo' ] ?? null;
		$targetDir = $this->resolveOutputDirectory( $outputDir );
		$this->log( sprintf( 'Packaging Shield plugin to: %s', $targetDir ) );

		// Clean target directory if it exists (unless skipped)
		if ( $options[ 'directory_clean' ] && is_dir( $targetDir ) ) {
			$this->log( sprintf( 'Cleaning existing package directory: %s', $targetDir ) );
			$this->directoryRemover->removeSafely( $targetDir );
		}

		$composerCommand = $this->commandRunner->getComposerCommand();
		if ( $options[ 'composer_install' ] ) {
			$this->commandRunner->run(
				array_merge( $composerCommand, [
					'install',
					'--no-interaction',
					'--prefer-dist',
				] ),
				$this->projectRoot
			);
		}

		if ( $options[ 'npm_install' ] ) {
			$this->commandRunner->run(
				[ 'npm', 'ci', '--no-audit', '--no-fund' ],
				$this->projectRoot
			);
		}

		if ( $options[ 'npm_build' ] ) {
			$this->commandRunner->run(
				[ 'npm', 'run', 'build' ],
				$this->projectRoot
			);
		}

		// Update SOURCE spec file FIRST (if version options provided)
		// This ensures buildPluginJson() reads the correct version
		$this->updateSourceSpec( $options );

		// Copy files (skip if already in place via git archive)
		if ( !$options[ 'skip_copy' ] ) {
			$this->fileCopier->copy( $targetDir );
		}
		else {
			$this->log( 'Skipping file copy (--skip-copy enabled)' );
		}

		// Generate plugin.json from updated spec files
		$this->buildPluginJson( $targetDir );

		// Update package-specific files (readme.txt, icwp-wpsf.php)
		$this->updatePackageFiles( $targetDir, $options );

		$this->installComposerDependencies( $targetDir );
		( new VendorCleaner( $this->logger ) )->clean( $targetDir );

		// Run Strauss prefixing
		$straussProvider = new StraussBinaryProvider(
			$this->straussVersion,
			$this->straussForkRepo,
			$this->commandRunner,
			$this->directoryRemover,
			$this->logger
		);
		$straussProvider->runPrefixing( $targetDir );

		$this->cleanupPackageFiles( $targetDir );
		$this->cleanAutoloadFiles( $targetDir );

		// Create legacy path duplicates for upgrade compatibility
		$this->createLegacyDuplicates( $targetDir );

		$this->verifyPackage( $targetDir );

		$this->log( sprintf( 'Package created at: %s', $targetDir ) );

		return $targetDir;
	}

	private function detectProjectRoot() :string {
		$root = realpath( __DIR__.'/../../../..' );
		return $root === false ? '' : $root;
	}

	private function resolveOutputDirectory( ?string $path ) :string {
		if ( $path === null || $path === '' ) {
			throw new \RuntimeException(
				'Output directory is required. Please specify --output=<directory> when packaging. '.
				'Packages must be built outside the project directory (e.g., SVN repository or external build directory).'
			);
		}

		// Trim quotes and whitespace that might come from command-line parsing
		$path = trim( $path, " \t\n\r\0\x0B\"'" );

		// Check for empty after trimming
		if ( $path === '' ) {
			throw new \RuntimeException(
				'Output directory is required. Please specify --output=<directory> when packaging. '.
				'Packages must be built outside the project directory (e.g., SVN repository or external build directory).'
			);
		}

		// Use Symfony Filesystem Path to normalize and check if absolute
		// Path::isAbsolute() handles Windows, Unix, and UNC paths correctly
		if ( !Path::isAbsolute( $path ) ) {
			// Relative path - prepend project root using Path::join for proper normalization
			$path = Path::join( $this->projectRoot, $path );
		}

		// Normalize the path using Symfony Filesystem (handles all edge cases)
		$resolved = Path::normalize( $path );

		// Try to resolve symlinks and get canonical path
		$realPath = realpath( $resolved );
		if ( $realPath !== false ) {
			$resolved = Path::normalize( $realPath );
		}
		else {
			// Directory doesn't exist yet, try to resolve parent directory
			// Use dirname() which works correctly with Path::normalize()
			$parentDir = dirname( $resolved );

			// Check if parent directory exists and can be resolved
			// Handle edge case where dirname() returns the same path (e.g., drive root)
			if ( $parentDir !== '' && $parentDir !== $resolved ) {
				$realParent = realpath( $parentDir );
				if ( $realParent !== false ) {
					$basename = basename( $resolved );
					$resolved = Path::join( Path::normalize( $realParent ), $basename );
				}
			}
		}

		return $resolved;
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function resolveOptions( array $options ) :array {
		$defaults = [
			'composer_install'  => true,
			'npm_install'       => true,
			'npm_build'         => true,
			'directory_clean'   => true,
			'skip_copy'         => false,
			'strauss_version'   => null,
			'strauss_fork_repo' => null,
			'version'           => null,
			'release_timestamp' => null,
			'build'             => null,
		];

		return array_replace( $defaults, array_intersect_key( $options, $defaults ) );
	}

	private function resolveStraussVersion( array $options ) :string {
		$fromOptions = $options[ 'strauss_version' ] ?? null;
		if ( is_string( $fromOptions ) && $fromOptions !== '' ) {
			return ltrim( $fromOptions, "v \t\n\r\0\x0B" );
		}

		$env = getenv( 'SHIELD_STRAUSS_VERSION' );
		if ( is_string( $env ) && $env !== '' ) {
			return ltrim( $env, "v \t\n\r\0\x0B" );
		}

		return StraussBinaryProvider::getFallbackVersion();
	}

	/**
	 * Generate plugin.json from spec files into the target package directory.
	 * Runs regardless of --skip-copy since plugin.json is gitignored and must be generated.
	 */
	private function buildPluginJson( string $targetDir ) :void {
		$specDir = Path::join( $this->projectRoot, 'plugin-spec' );
		$outputPath = Path::join( $targetDir, 'plugin.json' );

		$this->log( 'Generating plugin.json from plugin-spec/...' );
		( new ConfigMerger() )->mergeToFile( $specDir, $outputPath );
		$this->log( '  ✓ plugin.json generated' );
	}

	/**
	 * Update source plugin-spec/01_properties.json with version metadata.
	 * Must be called BEFORE buildPluginJson() so merged config has correct values.
	 *
	 * @param array<string, mixed> $options Package options including version, release_timestamp, build
	 */
	private function updateSourceSpec( array $options ) :void {
		$versionOptions = [];

		if ( $options[ 'version' ] !== null ) {
			$versionOptions[ 'version' ] = $options[ 'version' ];

			// Auto-generate timestamp if version provided but timestamp not
			if ( $options[ 'release_timestamp' ] === null ) {
				$versionOptions[ 'release_timestamp' ] = \time();
			}
		}

		if ( $options[ 'release_timestamp' ] !== null ) {
			$versionOptions[ 'release_timestamp' ] = $options[ 'release_timestamp' ];
		}

		if ( $options[ 'build' ] !== null ) {
			if ( $options[ 'build' ] === 'auto' ) {
				$versionOptions[ 'build' ] = $this->versionUpdater->generateBuild();
			}
			else {
				$versionOptions[ 'build' ] = $options[ 'build' ];
			}
		}

		if ( !empty( $versionOptions ) ) {
			$this->log( 'Updating source spec files...' );
			$this->versionUpdater->updateSourceProperties( $versionOptions );
			$this->log( '  ✓ Source spec updated' );
		}
	}

	/**
	 * Update package-specific files (readme.txt, icwp-wpsf.php) with version.
	 * Note: plugin.json already has correct version from buildPluginJson().
	 *
	 * @param array<string, mixed> $options Package options including version
	 */
	private function updatePackageFiles( string $targetDir, array $options ) :void {
		if ( $options[ 'version' ] !== null ) {
			$this->log( 'Updating package files...' );
			$this->versionUpdater->updateReadmeTxt( $targetDir, $options[ 'version' ] );
			$this->versionUpdater->updatePluginHeader( $targetDir, $options[ 'version' ] );
			$this->log( '  ✓ Package files updated' );
		}
	}

	/**
	 * Install composer dependencies in the package directory (production only).
	 *
	 * @throws \RuntimeException if composer install fails
	 */
	private function installComposerDependencies( string $targetDir ) :void {
		$this->log( 'Installing composer dependencies in package...' );

		if ( !file_exists( Path::join( $targetDir, 'composer.json' ) ) ) {
			throw new \RuntimeException(
				sprintf(
					'Composer install failed: composer.json does not exist in package directory. '.
					'Expected file: %s. '.
					'This usually means the file copy step did not complete successfully.',
					Path::join( $targetDir, 'composer.json' )
				)
			);
		}

		$composerCommand = $this->commandRunner->getComposerCommand();
		$this->commandRunner->run(
			array_merge( $composerCommand, [
				'install',
				'--no-dev',
				'--no-interaction',
				'--prefer-dist',
				'--quiet'
			] ),
			$targetDir
		);

		$this->log( '  ✓ Composer dependencies installed' );
		$this->log( '' );
	}

	/**
	 * Clean up duplicate and development files from the package.
	 * Removes files that are no longer needed after Strauss prefixing.
	 */
	private function cleanupPackageFiles( string $targetDir ) :void {
		$fs = new Filesystem();

		// Remove Strauss fork directory if it exists (only when not in Docker)
		// In Docker, we use /tmp which is ephemeral - no cleanup needed
		if ( $this->straussForkRepo !== null && !StraussBinaryProvider::isRunningInDocker() ) {
			$forkHash = substr( md5( $this->straussForkRepo ), 0, 12 );
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
		elseif ( $this->straussForkRepo !== null && StraussBinaryProvider::isRunningInDocker() ) {
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
	private function cleanAutoloadFiles( string $targetDir ) :void {
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

	/**
	 * Create legacy path duplicates for upgrade compatibility.
	 *
	 * During WordPress plugin upgrades, the old autoloader is still loaded in memory
	 * with the old PSR-4 paths. When shutdown hooks fire, PHP tries to autoload classes
	 * from the OLD paths but the NEW files are on disk. This method duplicates the classes
	 * that are autoloaded during shutdown to their legacy paths.
	 *
	 * @see Plan documentation for complete shutdown code trace
	 */
	private function createLegacyDuplicates( string $targetDir ) :void {
		$this->log( 'Creating legacy path duplicates for upgrade compatibility...' );

		$fs = new Filesystem();
		$srcDir = Path::join( $targetDir, 'src' );
		$legacySrcDir = Path::join( $targetDir, 'src', 'lib', 'src' );
		$vendorPrefixedDir = Path::join( $targetDir, 'vendor_prefixed' );
		$legacyVendorPrefixedDir = Path::join( $targetDir, 'src', 'lib', 'vendor_prefixed' );
		$vendorDir = Path::join( $targetDir, 'vendor' );
		$legacyVendorDir = Path::join( $targetDir, 'src', 'lib', 'vendor' );

		// Create legacy directory structure
		$fs->mkdir( $legacySrcDir, 0755 );
		$fs->mkdir( $legacyVendorPrefixedDir, 0755 );
		$fs->mkdir( $legacyVendorDir, 0755 );

		// ========================================
		// Source directories to mirror (full copy)
		// ========================================
		$srcDirectoriesToMirror = [
			[ 'Controller', 'Config' ],              // OptsHandler::store() at shutdown
			[ 'DBs' ],                               // All DB classes for logging/IP ops
			[ 'Logging' ],                           // All log processors
			[ 'Modules', 'IPs', 'Lib', 'IpRules' ],  // IP rule classes
		];

		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $srcDir, ...$path ),
				Path::join( $legacySrcDir, ...$path )
			),
			$srcDirectoriesToMirror
		);

		// ========================================
		// Individual source files to copy
		// ========================================
		$srcFilesToCopy = [
			// Controller/Dependencies/Monolog.php - Assessed when creating loggers
			[ 'Controller', 'Dependencies', 'Monolog.php' ],
			// Modules/AuditTrail/Lib/ - Audit logging
			[ 'Modules', 'AuditTrail', 'Lib', 'ActivityLogMessageBuilder.php' ],
			[ 'Modules', 'AuditTrail', 'Lib', 'LogHandlers', 'LocalDbWriter.php' ],
			// Modules/HackGuard/Lib/Snapshots/ - Snapshot checking
			[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'FindAssetsToSnap.php' ],
			[ 'Modules', 'HackGuard', 'Lib', 'Snapshots', 'StoreAction', 'Load.php' ],
			// Modules/IPs/Components/ProcessOffense.php - Offense processing
			[ 'Modules', 'IPs', 'Components', 'ProcessOffense.php' ],
			// Modules/IPs/Lib/Bots/BotSignalsRecord.php - Bot signal recording
			[ 'Modules', 'IPs', 'Lib', 'Bots', 'BotSignalsRecord.php' ],
			// Modules/Traffic/Lib/LogHandlers/LocalDbWriter.php - Traffic DB writing
			[ 'Modules', 'Traffic', 'Lib', 'LogHandlers', 'LocalDbWriter.php' ],
		];

		\array_map(
			function ( array $path ) use ( $fs, $srcDir, $legacySrcDir ) :void {
				$destPath = Path::join( $legacySrcDir, ...$path );
				$fs->mkdir( \dirname( $destPath ), 0755 );
				$fs->copy(
					Path::join( $srcDir, ...$path ),
					$destPath
				);
			},
			$srcFilesToCopy
		);

		// ========================================
		// Vendor prefixed directories to mirror
		// ========================================
		$vendorDirectoriesToMirror = [
			[ 'monolog' ],   // Monolog used at shutdown for logging
			[ 'composer' ],  // Autoloader metadata
		];

		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $vendorPrefixedDir, ...$path ),
				Path::join( $legacyVendorPrefixedDir, ...$path )
			),
			$vendorDirectoriesToMirror
		);

		// ========================================
		// Vendor prefixed files to copy
		// ========================================
		$vendorFilesToCopy = [
			'autoload.php',
			'autoload-classmap.php',
		];

		\array_map(
			function ( string $file ) use ( $fs, $vendorPrefixedDir, $legacyVendorPrefixedDir ) :void {
				$src = Path::join( $vendorPrefixedDir, $file );
				if ( \file_exists( $src ) ) {
					$fs->copy( $src, Path::join( $legacyVendorPrefixedDir, $file ) );
				}
			},
			$vendorFilesToCopy
		);

		// ========================================
		// Standard vendor directories to mirror (for shutdown compatibility)
		// ========================================
		$stdVendorDirectoriesToMirror = [
			[ 'mlocati', 'ip-lib' ],  // IPLib used by AddRule at shutdown
			[ 'composer' ],            // Autoloader metadata
		];

		\array_map(
			fn( array $path ) => $fs->mirror(
				Path::join( $vendorDir, ...$path ),
				Path::join( $legacyVendorDir, ...$path )
			),
			$stdVendorDirectoriesToMirror
		);

		// ========================================
		// Standard vendor files to copy
		// ========================================
		$stdVendorFilesToCopy = [
			'autoload.php',
		];

		\array_map(
			function ( string $file ) use ( $fs, $vendorDir, $legacyVendorDir ) :void {
				$src = Path::join( $vendorDir, $file );
				if ( \file_exists( $src ) ) {
					$fs->copy( $src, Path::join( $legacyVendorDir, $file ) );
				}
			},
			$stdVendorFilesToCopy
		);

		$this->log( '  ✓ Created legacy path duplicates for upgrade compatibility' );
	}

	/**
	 * Verify the package was built correctly by checking required files and directories.
	 *
	 * @throws \RuntimeException if critical package files are missing
	 */
	private function verifyPackage( string $targetDir ) :void {
		$this->log( '=== Package Verification ===' );

		$errors = [];

		// Required files
		$requiredFiles = [
			'plugin.json'         => Path::join( $targetDir, 'plugin.json' ),
			'icwp-wpsf.php'       => Path::join( $targetDir, 'icwp-wpsf.php' ),
			'vendor/autoload.php' => Path::join( $targetDir, 'vendor', 'autoload.php' ),
		];

		foreach ( $requiredFiles as $name => $path ) {
			if ( file_exists( $path ) ) {
				$this->log( sprintf( '✓ %s exists', $name ) );
			}
			else {
				$this->log( sprintf( '✗ %s MISSING', $name ) );
				$errors[] = $name;
			}
		}

		// Required directories
		$requiredDirs = [
			'vendor_prefixed'                => Path::join( $targetDir, 'vendor_prefixed' ),
			'assets/dist'                    => Path::join( $targetDir, 'assets', 'dist' ),
			'src/lib/src (legacy compat)'    => Path::join( $targetDir, 'src', 'lib', 'src' ),
			'src/lib/vendor (legacy compat)' => Path::join( $targetDir, 'src', 'lib', 'vendor' ),
		];

		foreach ( $requiredDirs as $name => $path ) {
			if ( is_dir( $path ) ) {
				$this->log( sprintf( '✓ %s directory exists', $name ) );
			}
			else {
				$this->log( sprintf( '✗ %s directory MISSING', $name ) );
				$errors[] = $name.' directory';
			}
		}

		if ( !empty( $errors ) ) {
			throw new \RuntimeException(
				sprintf(
					'Package verification failed: Missing critical files/directories. '.
					'WHAT FAILED: The following required items are missing: %s. '.
					'WHY: The packaging process may have encountered errors during file copy, '.
					'composer install, or Strauss prefixing. '.
					'HOW TO FIX: Check the log output above for errors and run the packaging process again.',
					implode( ', ', $errors )
				)
			);
		}

		$this->log( sprintf( '✅ Package built successfully: %s', $targetDir ) );
	}
}

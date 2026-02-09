<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use FernleafSystems\ShieldPlatform\Tooling\ConfigMerger;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
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

	private PostStraussCleanup $postStraussCleanup;

	private LegacyPathDuplicator $legacyPathDuplicator;

	private PackageVerifier $packageVerifier;

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
		$this->postStraussCleanup = new PostStraussCleanup( $this->directoryRemover, $this->logger );
		$this->legacyPathDuplicator = new LegacyPathDuplicator( $this->logger );
		$this->packageVerifier = new PackageVerifier( $this->logger );
	}

	/**
	 * @param array<string,bool> $options
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function package( ?string $outputDir = null, array $options = [] ) :string {
		$options = $this->resolveOptions( $options );
		$this->straussVersion = $this->resolveStraussVersion( $options );
		$this->straussForkRepo = $options[ 'strauss_fork_repo' ] ?? null;
		$targetDir = $this->resolveOutputDirectory( $outputDir );
		$this->log( sprintf( 'Packaging Shield plugin to: %s', $targetDir ) );

		// Clean the target directory if it exists (unless skipped)
		if ( $options[ 'directory_clean' ] && is_dir( $targetDir ) ) {
			$this->log( sprintf( 'Cleaning existing package directory: %s', $targetDir ) );
			$this->directoryRemover->removeSafely( $targetDir );
		}

		$composerCommand = $this->commandRunner->getComposerCommand();
		if ( $options[ 'composer_install' ] ) {
			$this->commandRunner->run(
				\array_merge( $composerCommand, [
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

		$this->postStraussCleanup->cleanPackageFiles( $targetDir, $this->straussForkRepo );
		$this->postStraussCleanup->cleanAutoloadFiles( $targetDir );

		$this->removeComposerFiles( $targetDir );

		// Create legacy path duplicates for upgrade compatibility
		$this->legacyPathDuplicator->createDuplicates( $targetDir );

		$this->packageVerifier->verify( $targetDir );

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
		$realPath = \realpath( $resolved );
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
					$basename = \basename( $resolved );
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
		return \array_replace( $defaults, \array_intersect_key( $options, $defaults ) );
	}

	private function resolveStraussVersion( array $options ) :string {
		$fromOptions = $options[ 'strauss_version' ] ?? null;
		if ( \is_string( $fromOptions ) && $fromOptions !== '' ) {
			return \ltrim( $fromOptions, "v \t\n\r\0\x0B" );
		}

		$env = \getenv( 'SHIELD_STRAUSS_VERSION' );
		if ( \is_string( $env ) && $env !== '' ) {
			return \ltrim( $env, "v \t\n\r\0\x0B" );
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
	 * @throws \InvalidArgumentException
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

		if ( !\file_exists( Path::join( $targetDir, 'composer.json' ) ) ) {
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

		// Set unique autoloader suffix to prevent class name conflicts when both
		// source and package vendor autoloaders exist in the same PHP process
		// (e.g., Docker package testing where /app and /package are both mounted).
		$this->commandRunner->run(
			\array_merge( $composerCommand, [ 'config', 'autoloader-suffix', 'ShieldPackage' ] ),
			$targetDir
		);

		$this->commandRunner->run(
			\array_merge( $composerCommand, [
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
	 * Remove composer files from the package directory.
	 * Called after all composer/Strauss operations are complete.
	 * These files are needed during packaging but not at runtime.
	 */
	private function removeComposerFiles( string $targetDir ) :void {
		$files = [ 'composer.json', 'composer.lock' ];
		foreach ( $files as $file ) {
			$path = Path::join( $targetDir, $file );
			if ( \file_exists( $path ) ) {
				\unlink( $path );
				$this->log( \sprintf( '  Removed %s from package', $file ) );
			}
		}
	}
}

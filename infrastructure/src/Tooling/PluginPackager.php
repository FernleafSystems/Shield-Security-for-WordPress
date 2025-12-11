<?php
declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class PluginPackager {

	private string $projectRoot;

	/** @var callable */
	private $logger;

	public function __construct( ?string $projectRoot = null, ?callable $logger = null ) {
		$root = $projectRoot ?? $this->detectProjectRoot();
		if ( $root === '' ) {
			throw new RuntimeException( 'Unable to determine project root.' );
		}
		$this->projectRoot = $root;
		$this->logger = $logger ?? static function ( string $message ) :void {
			echo $message.PHP_EOL;
		};
	}

	/**
	 * @param array<string,bool> $options
	 */
	public function package( ?string $outputDir = null, array $options = [] ) :string {
		$options = $this->resolveOptions( $options );
		$targetDir = $this->resolveOutputDirectory( $outputDir );
		$this->log( sprintf( 'Packaging Shield plugin to: %s', $targetDir ) );

		// Clean target directory if it exists (unless skipped)
		if ( $options[ 'directory_clean' ] && is_dir( $targetDir ) ) {
			$this->log( sprintf( 'Cleaning existing package directory: %s', $targetDir ) );
			$this->removeDirectorySafely( $targetDir );
		}

		$composerCommand = $this->getComposerCommand();
		if ( $options[ 'composer_root' ] ) {
			$this->runCommand(
				array_merge( $composerCommand, [
					'install',
					'--no-interaction',
					'--prefer-dist',
					'--optimize-autoloader'
				] ),
				$this->projectRoot
			);
		}

		if ( $options[ 'composer_lib' ] ) {
			$this->runCommand(
				array_merge( $composerCommand, [
					'install',
					'--no-interaction',
					'--prefer-dist',
					'--optimize-autoloader'
				] ),
				$this->projectRoot.'/src/lib'
			);
		}

		if ( $options[ 'npm_install' ] ) {
			$this->runCommand(
				[ 'npm', 'ci', '--no-audit', '--no-fund' ],
				$this->projectRoot
			);
		}

		if ( $options[ 'npm_build' ] ) {
			$this->runCommand(
				[ 'npm', 'run', 'build' ],
				$this->projectRoot
			);
		}

		// Build the package - either copy files or assume they're already in place
		if ( !$options[ 'skip_copy' ] ) {
			$this->copyPluginFiles( $targetDir );
		}
		else {
			$this->log( 'Skipping file copy (--skip-copy enabled, files assumed to be in place)' );
		}
		$this->installComposerDependencies( $targetDir );
		$this->downloadStraussPhar( $targetDir );
		$this->runStraussPrefixing( $targetDir );
		$this->cleanupPackageFiles( $targetDir );
		$this->cleanPoFiles( $targetDir );
		$this->cleanAutoloadFiles( $targetDir );
		$this->verifyPackage( $targetDir );

		$this->log( sprintf( 'Package created at: %s', $targetDir ) );

		return $targetDir;
	}

	private function detectProjectRoot() :string {
		$root = realpath( __DIR__.'/../../..' );
		return $root === false ? '' : $root;
	}

	private function resolveOutputDirectory( ?string $path ) :string {
		if ( $path === null || $path === '' ) {
			throw new RuntimeException(
				'Output directory is required. Please specify --output=<directory> when packaging. '.
				'Packages must be built outside the project directory (e.g., SVN repository or external build directory).'
			);
		}

		// Trim quotes and whitespace that might come from command-line parsing
		$path = trim( $path, " \t\n\r\0\x0B\"'" );

		// Check for empty after trimming
		if ( $path === '' ) {
			throw new RuntimeException(
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

	private function isAbsolutePath( string $path ) :bool {
		// Use Symfony Filesystem Path::isAbsolute() which handles all platforms correctly
		// Path should already be trimmed by caller, but trim again for safety
		$path = trim( $path, " \t\n\r\0\x0B\"'" );

		if ( $path === '' ) {
			return false;
		}

		return Path::isAbsolute( $path );
	}

	/**
	 * Convert Windows paths to Git Bash compatible format for bash execution
	 * Only converts Windows drive-letter paths (D:/...) to Git Bash format (/mnt/d/...)
	 * Leaves Unix paths unchanged (works on Linux, Docker, GitHub Actions, WSL)
	 */
	private function convertPathForBash( string $path ) :string {
		$normalized = Path::normalize( $path );

		// Only convert if it's a Windows drive-letter path (D:/...) and we're on Windows
		// Unix paths (/path/to/file) are left unchanged for Linux/Docker/GitHub Actions
		if ( PHP_OS_FAMILY === 'Windows' && preg_match( '#^([A-Za-z]):/#', $normalized, $matches ) ) {
			$drive = strtolower( $matches[ 1 ] );
			$rest = substr( $normalized, 3 ); // Remove "D:/"
			// Handle edge case where rest might be empty (unlikely but possible)
			if ( $rest === '' ) {
				return '/mnt/'.$drive.'/';
			}
			return '/mnt/'.$drive.'/'.$rest; // Add slash after drive letter
		}

		// Return as-is for Unix paths (Linux, Docker, GitHub Actions, WSL)
		return $normalized;
	}

	private function runCommand( array $parts, ?string $workingDir = null ) :void {
		// Build command string with proper escaping for cross-platform compatibility
		// Command names from PATH (like 'npm', 'composer') don't need quotes
		// But full paths (especially with spaces) do need quotes
		// Arguments always get quoted to handle spaces and special characters
		$command = array_shift( $parts );

		// Check if command is a full path (contains directory separators)
		// If so, quote it to handle spaces; otherwise leave unquoted for PATH resolution
		$needsQuoting = strpos( $command, DIRECTORY_SEPARATOR ) !== false
						|| strpos( $command, '/' ) !== false  // Handle Unix paths on Windows
						|| strpos( $command, '\\' ) !== false; // Handle Windows paths

		$commandPart = $needsQuoting ? escapeshellarg( $command ) : $command;
		$args = array_map( static function ( string $part ) :string {
			return escapeshellarg( $part );
		}, $parts );
		$commandString = $commandPart.( empty( $args ) ? '' : ' '.implode( ' ', $args ) );
		$this->log( '> '.$commandString );

		$previousCwd = getcwd();
		$revert = false;
		if ( $workingDir !== null && $workingDir !== '' && $previousCwd !== $workingDir ) {
			if ( !is_dir( $workingDir ) ) {
				throw new RuntimeException( sprintf( 'Working directory does not exist: %s', $workingDir ) );
			}
			if ( !@chdir( $workingDir ) ) {
				throw new RuntimeException( sprintf( 'Unable to change directory to: %s', $workingDir ) );
			}
			$revert = true;
		}

		try {
			passthru( $commandString, $exitCode );
		}
		finally {
			if ( $revert && is_string( $previousCwd ) && $previousCwd !== '' ) {
				@chdir( $previousCwd );
			}
		}

		if ( $exitCode !== 0 ) {
			throw new RuntimeException( sprintf( 'Command failed with exit code %d: %s', $exitCode, $commandString ) );
		}
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	/**
	 * @return array<string,bool>
	 */
	private function resolveOptions( array $options ) :array {
		$defaults = [
			'composer_root'   => true,
			'composer_lib'    => true,
			'npm_install'     => true,
			'npm_build'       => true,
			'directory_clean' => true,
			'skip_copy'       => false, // Skip file copying (use when files already in place via git archive)
		];

		return array_replace( $defaults, array_intersect_key( $options, $defaults ) );
	}

	/**
	 * @return string[]
	 */
	private function getComposerCommand() :array {
		$binary = getenv( 'COMPOSER_BINARY' );
		if ( !is_string( $binary ) || $binary === '' ) {
			$binary = 'composer';
		}

		$resolved = $this->resolveBinaryPath( $binary );
		if ( $this->endsWithPhar( $resolved ) ) {
			$php = PHP_BINARY ?: 'php';
			return [ $php, $resolved ];
		}

		return [ $resolved ];
	}

	private function resolveBinaryPath( string $binary ) :string {
		if ( $this->isAbsolutePath( $binary ) && file_exists( $binary ) ) {
			return $binary;
		}

		if ( strpos( $binary, '/' ) !== false || strpos( $binary, '\\' ) !== false ) {
			$fromRoot = $this->projectRoot.DIRECTORY_SEPARATOR.$binary;
			if ( file_exists( $fromRoot ) ) {
				return $fromRoot;
			}
		}

		$runtimeDir = getenv( 'COMPOSER_RUNTIME_BIN_DIR' );
		if ( is_string( $runtimeDir ) && $runtimeDir !== '' ) {
			$candidate = rtrim( $runtimeDir, '/\\' ).DIRECTORY_SEPARATOR.$binary;
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $binary;
	}

	private function endsWithPhar( string $path ) :bool {
		return substr( $path, -5 ) === '.phar';
	}

	/**
	 * Copy plugin files and directories to the target package directory
	 * Uses Symfony Filesystem for cross-platform compatibility and line ending preservation
	 * @throws RuntimeException if copy operations fail
	 */
	private function copyPluginFiles( string $targetDir ) :void {
		$this->log( 'Copying plugin files...' );

		$fs = new Filesystem();

		// Ensure target directory exists
		if ( !is_dir( $targetDir ) ) {
			$fs->mkdir( $targetDir );
		}

		// Root files to copy
		$rootFiles = [
			'icwp-wpsf.php',
			'plugin_init.php',
			'readme.txt',
			'plugin.json',
			'cl.json',
			'plugin_autoload.php',
			'plugin_compatibility.php',
			'uninstall.php',
			'unsupported.php',
		];

		foreach ( $rootFiles as $file ) {
			$sourcePath = Path::join( $this->projectRoot, $file );
			$targetPath = Path::join( $targetDir, $file );

			if ( file_exists( $sourcePath ) ) {
				try {
					$fs->copy( $sourcePath, $targetPath, true );
					$this->log( sprintf( '  ✓ Copied: %s', $file ) );
				}
				catch ( \Exception $e ) {
					throw new RuntimeException(
						sprintf(
							'Failed to copy file "%s" to package directory. '.
							'Source: %s. Target: %s. Error: %s',
							$file,
							$sourcePath,
							$targetPath,
							$e->getMessage()
						)
					);
				}
			}
		}

		$this->log( '' );
		$this->log( 'Copying directories...' );

		// Directories to copy
		$directories = [
			'src',
			'assets',
			'flags',
			'languages',
			'templates',
		];

		foreach ( $directories as $dir ) {
			$sourcePath = Path::join( $this->projectRoot, $dir );
			$targetPath = Path::join( $targetDir, $dir );

			if ( is_dir( $sourcePath ) ) {
				$this->log( sprintf( '  → Copying directory: %s', $dir ) );
				try {
					$fs->mirror( $sourcePath, $targetPath );
					$this->log( sprintf( '    ✓ Completed: %s', $dir ) );
				}
				catch ( \Exception $e ) {
					throw new RuntimeException(
						sprintf(
							'Failed to copy directory "%s" to package directory. '.
							'Source: %s. Target: %s. Error: %s',
							$dir,
							$sourcePath,
							$targetPath,
							$e->getMessage()
						)
					);
				}
			}
		}

		$this->log( '' );
		$this->log( 'Package structure created successfully' );
		$this->log( '' );
	}

	/**
	 * Install composer dependencies in the package directory (production only)
	 * @throws RuntimeException if composer install fails
	 */
	private function installComposerDependencies( string $targetDir ) :void {
		$this->log( 'Installing composer dependencies in package...' );

		$libDir = Path::join( $targetDir, 'src/lib' );

		if ( !is_dir( $libDir ) ) {
			throw new RuntimeException(
				sprintf(
					'Composer install failed: Package library directory does not exist. '.
					'Expected directory: %s. '.
					'This usually means the file copy step did not complete successfully.',
					$libDir
				)
			);
		}

		$composerCommand = $this->getComposerCommand();
		$this->runCommand(
			array_merge( $composerCommand, [
				'install',
				'--no-dev',
				'--no-interaction',
				'--prefer-dist',
				'--optimize-autoloader',
				'--quiet'
			] ),
			$libDir
		);

		$this->log( '  ✓ Composer dependencies installed' );
		$this->log( '' );
	}

	private const STRAUSS_VERSION = '0.19.4';
	private const STRAUSS_DOWNLOAD_URL = 'https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar';

	/**
	 * Download Strauss phar file for namespace prefixing
	 * @throws RuntimeException if download fails with detailed error message
	 */
	private function downloadStraussPhar( string $targetDir ) :void {
		$this->log( sprintf( 'Downloading Strauss v%s...', self::STRAUSS_VERSION ) );

		$libDir = Path::join( $targetDir, 'src/lib' );
		$targetPath = Path::join( $libDir, 'strauss.phar' );

		// Check if allow_url_fopen is enabled
		if ( !ini_get( 'allow_url_fopen' ) ) {
			throw new RuntimeException(
				'Strauss download failed: allow_url_fopen is disabled in PHP configuration. '.
				'WHAT FAILED: Unable to download strauss.phar from GitHub. '.
				'WHY: The PHP setting "allow_url_fopen" is disabled, which prevents PHP from downloading files from URLs. '.
				'HOW TO FIX: Enable allow_url_fopen in your php.ini file by setting "allow_url_fopen = On", '.
				'or contact your system administrator to enable this setting. '.
				'Alternatively, you can manually download strauss.phar from '.self::STRAUSS_DOWNLOAD_URL.' '.
				'and place it in the package src/lib directory.'
			);
		}

		// Create HTTP context for the download
		$context = stream_context_create( [
			'http' => [
				'method'          => 'GET',
				'timeout'         => 30,
				'user_agent'      => 'Shield-Plugin-Packager/1.0',
				'follow_location' => true,
			],
			'ssl'  => [
				'verify_peer'      => true,
				'verify_peer_name' => true,
			],
		] );

		// Attempt download with retries for transient network failures (e.g., GitHub 503)
		$maxRetries = 3;
		$retryDelay = 5; // seconds
		$content = false;
		$lastErrorMessage = 'Unknown error';

		for ( $attempt = 1; $attempt <= $maxRetries; $attempt++ ) {
			error_clear_last();
			$content = @file_get_contents( self::STRAUSS_DOWNLOAD_URL, false, $context );

			if ( $content !== false ) {
				break; // Success
			}

			$lastError = error_get_last();
			$lastErrorMessage = $lastError !== null ? $lastError[ 'message' ] : 'Unknown error';

			if ( $attempt < $maxRetries ) {
				$this->log( sprintf( '  ⚠️  Download attempt %d/%d failed: %s', $attempt, $maxRetries, $lastErrorMessage ) );
				$this->log( sprintf( '  Retrying in %d seconds...', $retryDelay ) );
				sleep( $retryDelay );
			}
		}

		if ( $content === false ) {
			throw new RuntimeException(
				sprintf(
					'Strauss download failed: Unable to download strauss.phar from GitHub after %d attempts. '.
					'WHAT FAILED: Could not retrieve file from %s. '.
					'WHY: %s. '.
					'This may be due to: (1) Network connectivity issues, (2) GitHub server unavailability, '.
					'(3) Firewall or proxy blocking the connection, (4) SSL certificate verification failure. '.
					'HOW TO FIX: Check your internet connection and try again. If the problem persists, '.
					'you can manually download strauss.phar from the URL above and place it at: %s',
					$maxRetries,
					self::STRAUSS_DOWNLOAD_URL,
					$lastErrorMessage,
					$targetPath
				)
			);
		}

		if ( $content === '' ) {
			throw new RuntimeException(
				sprintf(
					'Strauss download failed: Downloaded file is empty. '.
					'WHAT FAILED: The file downloaded from %s has zero bytes. '.
					'WHY: This usually indicates a server-side issue or the release file was not found. '.
					'HOW TO FIX: Verify that Strauss version %s exists at the download URL. '.
					'You can manually download strauss.phar and place it at: %s',
					self::STRAUSS_DOWNLOAD_URL,
					self::STRAUSS_VERSION,
					$targetPath
				)
			);
		}

		// Clear any previous errors before writing
		error_clear_last();

		// Write the downloaded content to file
		$bytesWritten = @file_put_contents( $targetPath, $content );

		if ( $bytesWritten === false ) {
			$lastError = error_get_last();
			$errorMessage = $lastError !== null ? $lastError[ 'message' ] : 'Unknown error';

			throw new RuntimeException(
				sprintf(
					'Strauss download failed: Could not write strauss.phar to disk. '.
					'WHAT FAILED: Unable to save downloaded file to %s. '.
					'WHY: %s. '.
					'This may be due to: (1) Insufficient disk space, (2) Directory does not exist, '.
					'(3) Insufficient file permissions, (4) Disk write protection. '.
					'HOW TO FIX: Ensure the target directory exists and is writable: %s',
					$targetPath,
					$errorMessage,
					$libDir
				)
			);
		}

		// Verify the file was written correctly
		if ( !file_exists( $targetPath ) ) {
			throw new RuntimeException(
				sprintf(
					'Strauss download failed: File was not created after write operation. '.
					'WHAT FAILED: strauss.phar does not exist at expected location after download. '.
					'Expected location: %s. '.
					'WHY: The file write operation reported success but the file is not present. '.
					'This is unusual and may indicate a filesystem issue. '.
					'HOW TO FIX: Check disk space and filesystem health. Try running the packaging again.',
					$targetPath
				)
			);
		}

		$fileSize = filesize( $targetPath );
		if ( $fileSize === 0 ) {
			throw new RuntimeException(
				sprintf(
					'Strauss download failed: Downloaded file has zero size. '.
					'WHAT FAILED: strauss.phar exists but contains no data. '.
					'File location: %s. '.
					'WHY: The file was created but the content was not written correctly. '.
					'HOW TO FIX: Delete the empty file and try running the packaging again. '.
					'If the problem persists, manually download from: %s',
					$targetPath,
					self::STRAUSS_DOWNLOAD_URL
				)
			);
		}

		$this->log( '  ✓ strauss.phar downloaded' );
		$this->log( '' );
	}

	/**
	 * Run Strauss to prefix vendor namespaces
	 * @throws RuntimeException if Strauss execution fails
	 */
	private function runStraussPrefixing( string $targetDir ) :void {
		$libDir = Path::join( $targetDir, 'src/lib' );
		$straussPath = Path::join( $libDir, 'strauss.phar' );
		$vendorPrefixedDir = Path::join( $libDir, 'vendor_prefixed' );

		// Verify strauss.phar exists
		if ( !file_exists( $straussPath ) ) {
			throw new RuntimeException(
				sprintf(
					'Strauss execution failed: strauss.phar not found. '.
					'WHAT FAILED: Cannot run Strauss namespace prefixing. '.
					'Expected location: %s. '.
					'WHY: The Strauss download step may have failed or the file was removed. '.
					'HOW TO FIX: Run the packaging process again to re-download strauss.phar.',
					$straussPath
				)
			);
		}

		$this->log( sprintf( 'Current directory: %s', $libDir ) );
		$this->log( sprintf( 'Checking for composer.json: %s', file_exists( Path::join( $libDir, 'composer.json' ) ) ? 'YES' : 'NO' ) );
		$this->log( '' );

		$this->log( 'Running Strauss prefixing...' );

		// Run strauss.phar using PHP
		$php = PHP_BINARY ?: 'php';
		$this->runCommand( [ $php, 'strauss.phar' ], $libDir );

		$this->log( '' );

		// Verify vendor_prefixed directory was created
		$this->log( '=== After Strauss ===' );
		if ( is_dir( $vendorPrefixedDir ) ) {
			$this->log( '✓ vendor_prefixed directory created' );
		}
		else {
			$this->log( '✗ vendor_prefixed NOT created' );
			throw new RuntimeException(
				sprintf(
					'Strauss execution failed: vendor_prefixed directory was not created. '.
					'WHAT FAILED: Strauss completed without error but did not create the expected output directory. '.
					'Expected directory: %s. '.
					'WHY: This may indicate a Strauss configuration issue in composer.json or no packages matched for prefixing. '.
					'HOW TO FIX: Check the "extra.strauss" configuration in %s/composer.json.',
					$vendorPrefixedDir,
					$libDir
				)
			);
		}
	}

	/**
	 * Clean up duplicate and development files from the package
	 * Removes files that are no longer needed after Strauss prefixing
	 */
	private function cleanupPackageFiles( string $targetDir ) :void {
		$fs = new Filesystem();
		$libDir = Path::join( $targetDir, 'src/lib' );

		// Directories to remove (duplicates after Strauss prefixing)
		$this->log( 'Removing duplicate libraries from main vendor...' );
		$directoriesToRemove = [
			Path::join( $libDir, 'vendor/twig' ),
			Path::join( $libDir, 'vendor/monolog' ),
			Path::join( $libDir, 'vendor/bin' ),
		];

		foreach ( $directoriesToRemove as $dir ) {
			if ( is_dir( $dir ) ) {
				try {
					$fs->remove( $dir );
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
			Path::join( $libDir, 'vendor_prefixed/autoload-files.php' ),
			Path::join( $libDir, 'strauss.phar' ),
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
	 * Clean autoload files to remove twig references
	 * Preserves original line endings (CRLF/LF)
	 */
	private function cleanPoFiles( string $targetDir ) :void {
		$fs = new Filesystem();
		try {
			foreach ( new \FilesystemIterator( Path::join( $targetDir, 'languages' ) ) as $fsItem ) {
				/** @var \SplFileInfo $fsItem */
				if ( $fsItem->isFile() && $fsItem->getExtension() === 'po' ) {
					$fs->remove( $fsItem->getPathname() );
				}
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * Clean autoload files to remove twig references
	 * Preserves original line endings (CRLF/LF)
	 */
	private function cleanAutoloadFiles( string $targetDir ) :void {
		$this->log( 'Cleaning autoload files...' );

		$composerDir = Path::join( $targetDir, 'src/lib/vendor/composer' );

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
	 * Verify the package was built correctly by checking required files and directories
	 * @throws RuntimeException if critical package files are missing
	 */
	private function verifyPackage( string $targetDir ) :void {
		$this->log( '=== Package Verification ===' );

		$errors = [];

		// Required files
		$requiredFiles = [
			'icwp-wpsf.php'                                 => Path::join( $targetDir, 'icwp-wpsf.php' ),
			'src/lib/vendor/autoload.php'                   => Path::join( $targetDir, 'src/lib/vendor/autoload.php' ),
			'src/lib/vendor_prefixed/autoload-classmap.php' => Path::join( $targetDir, 'src/lib/vendor_prefixed/autoload-classmap.php' ),
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
			'src/lib/vendor_prefixed' => Path::join( $targetDir, 'src/lib/vendor_prefixed' ),
			'assets/dist'             => Path::join( $targetDir, 'assets/dist' ),
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
			throw new RuntimeException(
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

	/**
	 * Safely remove a directory with comprehensive safety checks
	 * @throws RuntimeException if directory cannot be safely deleted
	 */
	private function removeDirectorySafely( string $dir ) :void {
		// Normalize and resolve the directory path
		$realDir = realpath( $dir );
		if ( $realDir === false ) {
			// Directory doesn't exist or is inaccessible - nothing to delete
			return;
		}

		// Safety check: Ensure we're not deleting the project root or critical directories
		$this->validateDirectoryIsSafeToDelete( $realDir );

		// Perform the actual deletion
		$this->removeDirectoryRecursive( $realDir );
	}

	/**
	 * Validate that a directory is safe to delete
	 * @throws RuntimeException if directory is unsafe to delete
	 */
	private function validateDirectoryIsSafeToDelete( string $dir ) :void {
		$normalizedDir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		$normalizedRoot = rtrim( str_replace( '\\', '/', $this->projectRoot ), '/' );
		$isWithinProject = strpos( $normalizedDir, $normalizedRoot ) === 0;

		// Safety rule: Never allow cleaning within the project directory
		// Packages should only be built in external directories (e.g., SVN repos)
		if ( $isWithinProject ) {
			throw new RuntimeException(
				sprintf(
					'ERROR: Cannot build package within project directory. '.
					'Packages must be built in external directories (e.g., SVN repository or separate build directory). '.
					'Reason: Directory cleaning is disabled within the project root for safety. '.
					'Target directory: %s '.
					'Project root: %s '.
					'Solution: Specify an external directory with --output=<external-path>',
					$dir,
					$this->projectRoot
				)
			);
		}

		// Validate external directories with basic safety checks
		$this->validateDirectoryOutsideProject( $normalizedDir );
	}

	/**
	 * Validate directories outside the project root with basic safety checks
	 * Allows external directories (like SVN repos) but prevents dangerous system deletions
	 * @throws RuntimeException if directory is unsafe to delete
	 */
	private function validateDirectoryOutsideProject( string $normalizedDir ) :void {
		// Prevent deletion of system root directories
		$dangerousPaths = [
			'/',           // Unix root
			'/bin',
			'/etc',
			'/usr',
			'/var',
			'/sys',
			'/proc',
			'/dev',
			'c:',          // Windows root (drive letter only)
			'c:/',
			'c:\\',
		];

		foreach ( $dangerousPaths as $dangerousPath ) {
			$normalizedDangerous = rtrim( str_replace( '\\', '/', strtolower( $dangerousPath ) ), '/' );
			$normalizedDirLower = strtolower( $normalizedDir );

			// Exact match or directory is the dangerous path itself
			if ( $normalizedDirLower === $normalizedDangerous ||
				 $normalizedDirLower === $normalizedDangerous.'/' ) {
				throw new RuntimeException(
					sprintf(
						'ERROR: Cannot build package in system root or critical system directory. '.
						'Reason: Deleting system directories would cause severe system damage. '.
						'Target directory: %s '.
						'Blocked path pattern: %s '.
						'Solution: Use a safe build directory outside system roots (e.g., /tmp/shield-package or C:\\Builds\\shield-package)',
						$normalizedDir,
						$dangerousPath
					)
				);
			}
		}

		// Prevent deletion of very short paths (likely system roots)
		// Paths shorter than 3 characters are suspicious
		$pathLength = strlen( $normalizedDir );
		if ( $pathLength < 3 ) {
			throw new RuntimeException(
				sprintf(
					'ERROR: Cannot build package in suspiciously short path (likely system root). '.
					'Reason: Path is too short (%d characters) and may be a system root directory. '.
					'Target directory: %s '.
					'Solution: Use a full path to a safe build directory (minimum 3 characters)',
					$pathLength,
					$normalizedDir
				)
			);
		}

		// Prevent deletion of Windows system directories
		$windowsSystemDirs = [
			'c:/windows',
			'c:/windows/system32',
			'c:/program files',
			'c:/program files (x86)',
		];

		foreach ( $windowsSystemDirs as $sysDir ) {
			$normalizedSysDir = strtolower( $sysDir );
			if ( strpos( strtolower( $normalizedDir ), $normalizedSysDir ) === 0 ) {
				throw new RuntimeException(
					sprintf(
						'ERROR: Cannot build package in Windows system directory. '.
						'Reason: Deleting Windows system directories would cause severe system damage. '.
						'Target directory: %s '.
						'Blocked system directory pattern: %s '.
						'Solution: Use a safe build directory outside Windows system paths (e.g., C:\\Builds\\shield-package or C:\\Temp\\shield-package)',
						$normalizedDir,
						$sysDir
					)
				);
			}
		}
	}

	/**
	 * Recursively remove a directory and all its contents
	 * @throws RuntimeException if deletion fails
	 */
	private function removeDirectoryRecursive( string $dir ) :void {
		if ( !is_dir( $dir ) ) {
			return;
		}

		// Read directory contents
		$files = @scandir( $dir );
		if ( $files === false ) {
			throw new RuntimeException(
				sprintf( 'Failed to read directory contents: %s', $dir )
			);
		}

		$files = array_diff( $files, [ '.', '..' ] );

		foreach ( $files as $file ) {
			$path = $dir.DIRECTORY_SEPARATOR.$file;

			// Check if it's a symlink before processing
			if ( is_link( $path ) ) {
				// For symlinks, only delete the link itself, not the target
				if ( !@unlink( $path ) ) {
					throw new RuntimeException(
						sprintf( 'Failed to delete symlink: %s', $path )
					);
				}
				continue;
			}

			if ( is_dir( $path ) ) {
				// Recursively delete subdirectories
				$this->removeDirectoryRecursive( $path );
			}
			else {
				// Delete files
				if ( !@unlink( $path ) ) {
					// Check if file still exists (might have been deleted by another process)
					if ( file_exists( $path ) ) {
						throw new RuntimeException(
							sprintf( 'Failed to delete file: %s', $path )
						);
					}
				}
			}
		}

		// Remove the directory itself
		if ( !@rmdir( $dir ) ) {
			// Check if directory still exists
			if ( is_dir( $dir ) ) {
				throw new RuntimeException(
					sprintf( 'Failed to delete directory: %s', $dir )
				);
			}
		}
	}
}

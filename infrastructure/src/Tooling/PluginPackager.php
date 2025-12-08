<?php
declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling;

use RuntimeException;
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
		$this->logger = $logger ?? static function( string $message ) :void {
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
		if ( $options['directory_clean'] && is_dir( $targetDir ) ) {
			$this->log( sprintf( 'Cleaning existing package directory: %s', $targetDir ) );
			$this->removeDirectorySafely( $targetDir );
		}

		$composerCommand = $this->getComposerCommand();
		if ( $options['composer_root'] ) {
			$this->runCommand(
				array_merge( $composerCommand, ['install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'] ),
				$this->projectRoot
			);
		}

		if ( $options['composer_lib'] ) {
			$this->runCommand(
				array_merge( $composerCommand, ['install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'] ),
				$this->projectRoot.'/src/lib'
			);
		}

		if ( $options['npm_install'] ) {
			$this->runCommand(
				['npm', 'ci', '--no-audit', '--no-fund'],
				$this->projectRoot
			);
		}

		if ( $options['npm_build'] ) {
			$this->runCommand(
				['npm', 'run', 'build'],
				$this->projectRoot
			);
		}

		// Use relative path for script since runCommand() changes directory to project root before executing
		// Convert Windows paths to Git Bash format (/mnt/d/...) for arguments passed to bash
		// This works on Windows (Git Bash), Linux, Docker, and GitHub Actions
		$bashTargetDir = $this->convertPathForBash( $targetDir );
		$bashProjectRoot = $this->convertPathForBash( $this->projectRoot );
		
		$this->runCommand(
			['bash', 'bin/build-package.sh', $bashTargetDir, $bashProjectRoot],
			$this->projectRoot
		);

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
			$drive = strtolower( $matches[1] );
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
			'composer_root' => true,
			'composer_lib' => true,
			'npm_install' => true,
			'npm_build' => true,
			'directory_clean' => true,
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
			return [$php, $resolved];
		}

		return [$resolved];
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

		$files = array_diff( $files, ['.', '..'] );
		
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

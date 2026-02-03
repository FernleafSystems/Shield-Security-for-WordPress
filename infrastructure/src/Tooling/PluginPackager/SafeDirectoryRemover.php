<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Safe directory removal with comprehensive validation to prevent accidental system damage.
 */
class SafeDirectoryRemover {

	private string $projectRoot;

	public function __construct( string $projectRoot ) {
		$this->projectRoot = $projectRoot;
	}

	/**
	 * Safely remove a directory with comprehensive safety checks.
	 *
	 * @throws \RuntimeException if directory cannot be safely deleted
	 */
	public function removeSafely( string $dir ) :void {
		$realDir = \realpath( $dir );
		if ( $realDir !== false ) {
			$this->validateDirectoryIsSafeToDelete( $realDir );
			FileSystemUtils::removeDirectoryRecursive( $realDir );
		}
	}

	/**
	 * Safely remove a directory that must be inside a specified parent directory.
	 *
	 * @throws \RuntimeException if directory is not inside parent or deletion fails
	 */
	public function removeSubdirectoryOf( string $dir, string $mustBeInsideDir ) :void {
		$realDir = \realpath( $dir );
		$realParent = \realpath( $mustBeInsideDir );

		if ( $realDir === false ) {
			return; // Directory doesn't exist, nothing to delete
		}

		if ( $realParent === false ) {
			throw new \RuntimeException( sprintf( 'Parent directory does not exist: %s', $mustBeInsideDir ) );
		}

		// Normalize paths for comparison
		$normalizedDir = rtrim( str_replace( '\\', '/', $realDir ), '/' );
		$normalizedParent = rtrim( str_replace( '\\', '/', $realParent ), '/' );

		// Verify the directory is actually inside the parent
		if ( strpos( $normalizedDir, $normalizedParent.'/' ) !== 0 ) {
			throw new \RuntimeException(
				sprintf(
					'SAFETY CHECK FAILED: Refusing to delete directory that is not inside expected parent. '.
					'Directory: %s | Expected parent: %s',
					$realDir,
					$realParent
				)
			);
		}

		// Also verify it's not the parent itself
		if ( $normalizedDir === $normalizedParent ) {
			throw new \RuntimeException(
				sprintf( 'SAFETY CHECK FAILED: Cannot delete the parent directory itself: %s', $realDir )
			);
		}

		FileSystemUtils::removeDirectoryRecursive( $realDir );
	}

	/**
	 * Remove a temporary directory safely (less strict than removeSafely).
	 *
	 * @param string      $dir             Directory to remove
	 * @param string|null $allowedBasePath Additional allowed base path (besides system temp)
	 */
	public function removeTempDirectory( string $dir, ?string $allowedBasePath = null ) :void {
		$realDir = realpath( $dir );
		if ( $realDir === false ) {
			return;
		}

		$normalizedDir = Path::normalize( $realDir );

		// Check if inside system temp directory
		$tempDir = Path::normalize( sys_get_temp_dir() );
		$isInTemp = Path::isBasePath( $tempDir, $normalizedDir );

		// Check if inside allowed base path (e.g., target directory for Strauss fork)
		$isInAllowed = false;
		if ( $allowedBasePath !== null ) {
			$realAllowed = realpath( $allowedBasePath );
			if ( $realAllowed !== false ) {
				$isInAllowed = Path::isBasePath( Path::normalize( $realAllowed ), $normalizedDir );
			}
		}

		if ( !$isInTemp && !$isInAllowed ) {
			throw new \RuntimeException( sprintf( 'Refusing to delete directory outside allowed paths: %s', $dir ) );
		}

		FileSystemUtils::removeDirectoryRecursive( $realDir );
	}

	/**
	 * Validate that a directory is safe to delete.
	 *
	 * @throws \RuntimeException if directory is unsafe to delete
	 */
	private function validateDirectoryIsSafeToDelete( string $dir ) :void {
		$normalizedDir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		$normalizedRoot = rtrim( str_replace( '\\', '/', $this->projectRoot ), '/' );
		$isWithinProject = strpos( $normalizedDir, $normalizedRoot ) === 0;

		// Safety rule: Never allow cleaning within the project directory
		// Packages should only be built in external directories (e.g., SVN repos)
		if ( $isWithinProject ) {
			throw new \RuntimeException(
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
	 * Validate directories outside the project root with basic safety checks.
	 * Allows external directories (like SVN repos) but prevents dangerous system deletions.
	 *
	 * @throws \RuntimeException if directory is unsafe to delete
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
				throw new \RuntimeException(
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
			throw new \RuntimeException(
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

		foreach ( \array_map( '\strtolower', $windowsSystemDirs ) as $sysDir ) {
			if ( \strpos( \strtolower( $normalizedDir ), $sysDir ) === 0 ) {
				throw new \RuntimeException(
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
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Verifies the package was built correctly by checking required files and directories.
 */
class PackageVerifier {

	private LegacyPathCompatibilityPlan $legacyPathCompatibilityPlan;

	/** @var callable */
	private $logger;

	public function __construct( LegacyPathCompatibilityPlan $legacyPathCompatibilityPlan, callable $logger ) {
		$this->legacyPathCompatibilityPlan = $legacyPathCompatibilityPlan;
		$this->logger = $logger;
	}

	/**
	 * @param string[] $requiredPrefixedPackages
	 */
	public function verify( string $targetDir, array $requiredPrefixedPackages = [] ) :void {
		$this->log( '=== Package Verification ===' );

		$errors = [];

		$requiredFiles = [
			'plugin.json'         => Path::join( $targetDir, 'plugin.json' ),
			'icwp-wpsf.php'       => Path::join( $targetDir, 'icwp-wpsf.php' ),
			'vendor/autoload.php' => Path::join( $targetDir, 'vendor', 'autoload.php' ),
		];

		foreach ( $requiredFiles as $name => $path ) {
			if ( \is_file( $path ) ) {
				$this->log( \sprintf( '✓ %s exists', $name ) );
			}
			else {
				$this->log( \sprintf( '✗ %s MISSING', $name ) );
				$errors[] = $name;
			}
		}

		$requiredDirs = [
			'vendor_prefixed' => Path::join( $targetDir, 'vendor_prefixed' ),
			'assets/dist'     => Path::join( $targetDir, 'assets', 'dist' ),
		];

		foreach ( $requiredDirs as $name => $path ) {
			if ( \is_dir( $path ) ) {
				$this->log( \sprintf( '✓ %s directory exists', $name ) );
			}
			else {
				$this->log( \sprintf( '✗ %s directory MISSING', $name ) );
				$errors[] = $name.' directory';
			}
		}

		$legacyRootDir = $this->legacyPathCompatibilityPlan->legacyRootDir( $targetDir );
		if ( !$this->legacyPathCompatibilityPlan->hasWork() ) {
			if ( \file_exists( $legacyRootDir ) ) {
				$this->log( '✗ src/lib legacy compatibility output should be absent' );
				$errors[] = 'src/lib legacy compatibility output';
			}
		}
		else {
			foreach ( $this->legacyPathCompatibilityPlan->expectedDirectoryOutputs( $targetDir ) as $path ) {
				$relativePath = Path::makeRelative( $path, $targetDir );
				if ( \is_dir( $path ) ) {
					$this->log( \sprintf( '✓ %s directory exists', $relativePath ) );
				}
				else {
					$this->log( \sprintf( '✗ %s directory MISSING', $relativePath ) );
					$errors[] = $relativePath.' directory';
				}
			}

			foreach ( $this->legacyPathCompatibilityPlan->expectedFileOutputs( $targetDir ) as $path ) {
				$relativePath = Path::makeRelative( $path, $targetDir );
				if ( \is_file( $path ) ) {
					$this->log( \sprintf( '✓ %s file exists', $relativePath ) );
				}
				else {
					$this->log( \sprintf( '✗ %s file MISSING', $relativePath ) );
					$errors[] = $relativePath.' file';
				}
			}
		}

		foreach ( $requiredPrefixedPackages as $package ) {
			if ( !\is_string( $package ) || $package === '' ) {
				continue;
			}

			$package = \strtolower( $package );
			$packageDir = Path::join( $targetDir, 'vendor_prefixed', $package );
			if ( \is_dir( $packageDir ) && !$this->isDirectoryEmpty( $packageDir ) ) {
				$this->log( \sprintf( 'PASS vendor_prefixed package exists: %s', $package ) );
			}
			else {
				$this->log( \sprintf( 'FAIL vendor_prefixed package MISSING: %s', $package ) );
				$errors[] = 'vendor_prefixed/'.$package;
			}
		}

		if ( !empty( $errors ) ) {
			throw new \RuntimeException(
				\sprintf(
					'Package verification failed: Missing critical files/directories. '.
					'WHAT FAILED: The following required items are missing: %s. '.
					'WHY: The packaging process may have encountered errors during file copy, '.
					'composer install, or Strauss prefixing. '.
					'HOW TO FIX: Check the log output above for errors and run the packaging process again.',
					\implode( ', ', $errors )
				)
			);
		}

		$this->log( \sprintf( '✅ Package built successfully: %s', $targetDir ) );
	}

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}

	private function isDirectoryEmpty( string $dir ) :bool {
		$contents = @\scandir( $dir );
		if ( $contents === false ) {
			return true;
		}

		return \count( \array_diff( $contents, [ '.', '..' ] ) ) === 0;
	}
}

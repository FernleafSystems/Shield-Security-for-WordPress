<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

/**
 * Verifies the package was built correctly by checking required files and directories.
 */
class PackageVerifier {

	/** @var callable */
	private $logger;

	public function __construct( callable $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Verify the package was built correctly by checking required files and directories.
	 *
	 * @throws \RuntimeException if critical package files are missing
	 */
	public function verify( string $targetDir ) :void {
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

	private function log( string $message ) :void {
		( $this->logger )( $message );
	}
}

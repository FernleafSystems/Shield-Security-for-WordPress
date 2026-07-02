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
			'plugin.json'                      => Path::join( $targetDir, 'plugin.json' ),
			'icwp-wpsf.php'                    => Path::join( $targetDir, 'icwp-wpsf.php' ),
			'vendor/autoload.php'              => Path::join( $targetDir, 'vendor', 'autoload.php' ),
			'src/Modules/Plugin/Processor.php' => Path::join( $targetDir, 'src', 'Modules', 'Plugin', 'Processor.php' ),
			'src/Components/ComponentLoader.php' => Path::join( $targetDir, 'src', 'Components', 'ComponentLoader.php' ),
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
			'templates/twig'  => Path::join( $targetDir, 'templates', 'twig' ),
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

		$templateRoot = Path::join( $targetDir, 'templates', 'twig' );
		if ( \is_dir( $templateRoot ) ) {
			$twigVerifier = new TwigTemplateReferenceVerifier();
			$missingReferences = $twigVerifier->findMissingReferences( $templateRoot );
			if ( empty( $missingReferences ) ) {
				$this->log( '✓ Twig static template references resolve' );
			}
			else {
				$details = $twigVerifier->formatMissingReferences( $missingReferences );
				$this->log( \sprintf( '✗ Twig static template references MISSING: %s', $details ) );
				$errors[] = 'Twig static template references: '.$details;
			}
		}

		$processorPath = Path::join( $targetDir, 'src', 'Modules', 'Plugin', 'Processor.php' );
		$componentLoaderPath = Path::join( $targetDir, 'src', 'Components', 'ComponentLoader.php' );
		$componentVerifier = new ProcessorComponentReferenceVerifier();
		if ( \is_file( $processorPath ) && \is_file( $componentLoaderPath ) ) {
			$missingComponentKeys = $componentVerifier->findMissingComponentKeys( $processorPath, $componentLoaderPath );
			if ( empty( $missingComponentKeys ) ) {
				$this->log( '✓ Processor component references are mapped' );
			}
			else {
				$details = $componentVerifier->formatMissingKeys( $missingComponentKeys );
				$this->log( \sprintf( '✗ Processor component references MISSING from ComponentLoader: %s', $details ) );
				$errors[] = 'Processor component references missing from ComponentLoader: '.$details;
			}
		}
		if ( \is_file( $componentLoaderPath ) ) {
			$missingComponentClassFiles = $componentVerifier->findMissingComponentClassFiles( $componentLoaderPath, $targetDir );
			if ( empty( $missingComponentClassFiles ) ) {
				$this->log( 'PASS ComponentLoader mapped class files exist' );
			}
			else {
				$details = $componentVerifier->formatMissingClassFiles( $missingComponentClassFiles );
				$this->log( \sprintf( 'FAIL ComponentLoader mapped class files MISSING: %s', $details ) );
				$errors[] = 'ComponentLoader mapped class files missing: '.$details;
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
					'Package verification failed: Required package checks failed. '.
					'WHAT FAILED: The following required checks failed: %s. '.
					'WHY: The packaging process may have encountered errors during file copy, '.
					'composer install, Strauss prefixing, or source/package coherence validation. '.
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

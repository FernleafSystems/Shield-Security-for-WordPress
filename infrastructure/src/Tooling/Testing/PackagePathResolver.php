<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class PackagePathResolver {

	private const DEFAULT_PACKAGE_DIRNAME_PREFIX = 'shield-package-cli-';

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	public function resolve( string $rootDir, ?string $packagePath = null ) :string {
		$normalized = $this->normalizeOptionalPath( $packagePath );
		if ( $normalized !== '' ) {
			$this->assertPackagePathIsValid( $normalized );
			return $normalized;
		}

		$defaultAbsolutePath = $this->buildDefaultPackagePath( $rootDir );
		$this->buildDefaultPackage( $rootDir, $defaultAbsolutePath );
		$this->assertPackagePathIsValid( $defaultAbsolutePath );

		return $defaultAbsolutePath;
	}

	private function buildDefaultPackagePath( string $rootDir ) :string {
		$tempRoot = \sys_get_temp_dir();
		if ( !\is_string( $tempRoot ) || $tempRoot === '' ) {
			throw new \RuntimeException( 'Unable to resolve system temp directory for package build output.' );
		}

		$hash = \substr( \md5( Path::normalize( $rootDir ) ), 0, 10 );
		return Path::normalize( Path::join( $tempRoot, self::DEFAULT_PACKAGE_DIRNAME_PREFIX.$hash ) );
	}

	private function buildDefaultPackage( string $rootDir, string $defaultPackagePath ) :void {
		$process = $this->processRunner->run(
			[
				\PHP_BINARY,
				'./bin/package-plugin.php',
				'--output='.$defaultPackagePath,
			],
			$rootDir
		);

		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			throw new \RuntimeException(
				'Failed to build plugin package into '.$defaultPackagePath.
				'. Provide a valid --package-path or run composer package-plugin.'
			);
		}
	}

	private function assertPackagePathIsValid( string $packagePath ) :void {
		if ( !\is_dir( $packagePath ) ) {
			throw new \RuntimeException(
				'Package path is not a directory: '.$packagePath.
				'. Provide a valid --package-path or omit it to build a package automatically.'
			);
		}

		$requiredFiles = [
			'icwp-wpsf.php',
			'vendor/autoload.php',
			'vendor_prefixed/autoload.php',
		];

		foreach ( $requiredFiles as $requiredFile ) {
			$requiredPath = Path::join( $packagePath, $requiredFile );
			if ( !\is_file( $requiredPath ) ) {
				throw new \RuntimeException(
					'Package path is missing required file: '.$requiredPath.
					'. Provide a valid built package path or run composer package-plugin.'
				);
			}
		}
	}

	private function normalizeOptionalPath( ?string $value ) :string {
		if ( !\is_string( $value ) ) {
			return '';
		}
		$trimmed = \trim( $value, " \t\n\r\0\x0B\"'" );
		return $trimmed === '' ? '' : Path::normalize( $trimmed );
	}
}

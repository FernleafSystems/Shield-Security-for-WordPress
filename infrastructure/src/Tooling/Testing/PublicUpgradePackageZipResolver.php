<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class PublicUpgradePackageZipResolver {

	private ProcessRunner $processRunner;

	private PublicUpgradePackageZipMetadataReader $metadataReader;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?PublicUpgradePackageZipMetadataReader $metadataReader = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->metadataReader = $metadataReader ?? new PublicUpgradePackageZipMetadataReader();
	}

	public function resolve(
		string $rootDir,
		?string $packageZip,
		PublicUpgradeArtifacts $artifacts,
		?callable $onOutput = null
	) :PublicUpgradePackageZipMetadata {
		$zipPath = $this->normalizeOptionalPath( $rootDir, $packageZip );
		if ( $zipPath === null ) {
			$zipPath = $artifacts->path( 'wp-simple-firewall-current.zip' );
			$this->buildPackageZip( $rootDir, $zipPath, $onOutput );
		}

		return $this->metadataReader->read( $zipPath );
	}

	private function buildPackageZip( string $rootDir, string $outputZip, ?callable $onOutput = null ) :void {
		$process = $this->processRunner->run(
			[
				\PHP_BINARY,
				'./bin/build-zip.php',
				'--output='.$outputZip,
				'--zip-root-folder=wp-simple-firewall',
			],
			$rootDir,
			$onOutput
		);

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( 'Failed to build current Shield package zip at: '.$outputZip );
		}
	}

	private function normalizeOptionalPath( string $rootDir, ?string $path ) :?string {
		if ( !\is_string( $path ) ) {
			return null;
		}

		$trimmed = \trim( $path, " \t\n\r\0\x0B\"'" );
		if ( $trimmed === '' ) {
			return null;
		}

		$normalized = Path::normalize( $trimmed );
		return Path::isAbsolute( $normalized ) ? $normalized : Path::makeAbsolute( $normalized, $rootDir );
	}
}

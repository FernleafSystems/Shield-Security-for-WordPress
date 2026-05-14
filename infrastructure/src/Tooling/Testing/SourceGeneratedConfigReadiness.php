<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class SourceGeneratedConfigReadiness {

	private ProcessRunner $processRunner;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	public function ensureReady(
		string $rootDir,
		?callable $onOutput = null,
		string $failureContext = 'source tooling'
	) :void {
		$setup = $this->setupCacheCoordinator->evaluateAnalyzeSetup( $rootDir );
		if ( $setup[ 'needs_build_config' ] ) {
			$process = $this->processRunner->run(
				[ \PHP_BINARY, './bin/build-config.php' ],
				$rootDir,
				$onOutput
			);
			$exitCode = $process->getExitCode() ?? 1;
			if ( $exitCode !== 0 ) {
				$errorOutput = \trim( $process->getErrorOutput() );
				throw new \RuntimeException(
					'Failed to regenerate plugin.json for '.$failureContext.'.'
					.( $errorOutput !== '' ? ' '.$errorOutput : '' )
				);
			}
			$this->setupCacheCoordinator->persistBuildConfigState( $rootDir, $setup[ 'fingerprint' ] );
		}

		$this->assertMetadataConsistency( $rootDir );
	}

	private function assertMetadataConsistency( string $rootDir ) :void {
		$sourceProperties = $this->decodeJsonFile(
			Path::join( $rootDir, 'plugin-spec', '01_properties.json' ),
			'Source properties spec'
		);
		$pluginConfig = $this->decodeJsonFile(
			Path::join( $rootDir, 'plugin.json' ),
			'Generated plugin config'
		);
		$headerVersion = $this->extractPluginHeaderVersion(
			Path::join( $rootDir, 'icwp-wpsf.php' )
		);

		$sourceVersion = (string)( $sourceProperties[ 'version' ] ?? '' );
		$sourceBuild = (string)( $sourceProperties[ 'build' ] ?? '' );
		$configVersion = (string)( $pluginConfig[ 'properties' ][ 'version' ] ?? '' );
		$configBuild = (string)( $pluginConfig[ 'properties' ][ 'build' ] ?? '' );

		if ( $sourceVersion === '' || $configVersion === '' ) {
			throw new \RuntimeException(
				'Local source metadata is incomplete: plugin-spec/01_properties.json and plugin.json must define version.'
			);
		}
		if ( $sourceVersion !== $configVersion || $sourceBuild !== $configBuild ) {
			throw new \RuntimeException(
				'Generated plugin.json is out of sync with plugin-spec/01_properties.json. '
				."Run 'composer build:config' and keep generated config current before local site or browser runs."
			);
		}
		if ( $headerVersion === '' || $headerVersion !== $configVersion ) {
			throw new \RuntimeException(
				'Generated plugin.json and icwp-wpsf.php plugin header are out of sync. '
				.'Update source release metadata so active artifacts agree before local site or browser runs.'
			);
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonFile( string $path, string $label ) :array {
		if ( !\is_file( $path ) ) {
			throw new \RuntimeException( $label.' is missing: '.$path );
		}

		$content = \file_get_contents( $path );
		if ( !\is_string( $content ) || $content === '' ) {
			throw new \RuntimeException( $label.' could not be read: '.$path );
		}

		$decoded = \json_decode( $content, true );
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException(
				$label.' is invalid JSON: '.$path.' ('.\json_last_error_msg().')'
			);
		}

		return $decoded;
	}

	private function extractPluginHeaderVersion( string $path ) :string {
		if ( !\is_file( $path ) ) {
			throw new \RuntimeException( 'Plugin root file icwp-wpsf.php is missing.' );
		}

		$content = \file_get_contents( $path );
		if ( !\is_string( $content ) || $content === '' ) {
			throw new \RuntimeException( 'Failed to read icwp-wpsf.php plugin header.' );
		}

		if ( !\preg_match( '/^\s*\*\s*Version:\s*(\S+)\s*$/mi', $content, $matches ) ) {
			throw new \RuntimeException( 'Failed to parse Version from icwp-wpsf.php plugin header.' );
		}

		return \trim( (string)$matches[ 1 ] );
	}
}

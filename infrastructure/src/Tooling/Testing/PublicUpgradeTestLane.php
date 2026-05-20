<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class PublicUpgradeTestLane {

	public const EXIT_PASS = 0;
	public const EXIT_TEST_FAILURE = 1;
	public const EXIT_SETUP_FAILURE = 2;
	public const EXIT_VERSION_GATE = 3;

	private const PLUGIN_SLUG = 'wp-simple-firewall';
	private const PLUGIN_FILE = 'wp-simple-firewall/icwp-wpsf.php';
	private const FIXTURE_DIR = '/app/tests/fixtures/upgrade-public/';

	private PublicUpgradePackageZipResolver $packageZipResolver;

	private WordPressPackageRuntimeHarness $runtime;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteProbe $probe = null,
		?PublicUpgradePackageZipResolver $packageZipResolver = null,
		?PackageRuntimeLogScanner $logScanner = null
	) {
		$processRunner = $processRunner ?? new ProcessRunner();
		$this->packageZipResolver = $packageZipResolver ?? new PublicUpgradePackageZipResolver( $processRunner );
		$this->runtime = new WordPressPackageRuntimeHarness(
			'upgrade-public',
			'Shield Upgrade Public Test',
			'SHIELD_UPGRADE_TEST_COMPOSE_PROJECT',
			'shield-upgrade-public',
			'SHIELD_UPGRADE_TEST_SITE_PORT',
			'8894',
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$probe,
			$logScanner
		);
	}

	public function run(
		string $rootDir,
		?string $packageZip = null,
		?string $artifactDir = null,
		bool $showDockerOutput = false
	) :int {
		$artifacts = PublicUpgradeArtifacts::resolve( $rootDir, $artifactDir, $showDockerOutput );
		$artifacts->resetForRun();

		$summary = $artifacts->baseSummary( self::EXIT_SETUP_FAILURE );
		$envOverrides = null;
		$exitCode = self::EXIT_SETUP_FAILURE;
		$runtimeArtifactsCollected = false;

		try {
			echo 'Mode: upgrade-public'.\PHP_EOL;
			echo 'Artifact directory: '.$artifacts->dir().\PHP_EOL;

			$this->runtime->assertDockerReady( $rootDir );
			$envOverrides = $this->runtime->buildDockerEnvOverrides( $rootDir );
			$metadata = $this->packageZipResolver->resolve(
				$rootDir,
				$packageZip,
				$artifacts,
				$artifacts->processOutputCallback()
			);
			$summary = $artifacts->withPackageMetadata( $summary, $metadata );

			$this->runtime->prepareCleanSite( $rootDir, $envOverrides, $artifacts, $showDockerOutput, true );

			$publicInfo = $this->runtime->runJsonFixture(
				$rootDir,
				$envOverrides,
				$artifacts,
				$this->fixture( 'public-plugin-info.php' )
			);
			$summary[ 'public_info' ] = $publicInfo;
			$artifacts->writeJson( PublicUpgradeArtifacts::PUBLIC_VERSION_FILE, $publicInfo );

			$this->installPublicPlugin( $rootDir, $envOverrides, $artifacts );
			$publicVersion = $this->runtime->readPluginVersion(
				$rootDir,
				$envOverrides,
				$artifacts,
				self::PLUGIN_SLUG,
				'Read installed public Shield version'
			);
			$summary[ 'installed_public_version' ] = $publicVersion;
			$this->assertPublicVersionMatchesMetadata( $publicVersion, $publicInfo );
			if ( \version_compare( $metadata->version(), $publicVersion, '<=' ) ) {
				throw new PublicUpgradeVersionGateException(
					'Current package version '.$metadata->version().' is not greater than public version '.$publicVersion.'.'
				);
			}

			$this->runtime->publishPackageZip( $rootDir, $envOverrides, $artifacts, $metadata );
			$this->writeUpdateConfig( $rootDir, $envOverrides, $artifacts, $metadata );
			$this->verifyPackageUrl( $rootDir, $envOverrides, $artifacts );

			$primingReport = $this->runtime->runJsonFixture(
				$rootDir,
				$envOverrides,
				$artifacts,
				$this->fixture( 'prime-shield-options.php' )
			);
			$summary[ 'priming_report' ] = $primingReport;
			$artifacts->writeJson( PublicUpgradeArtifacts::PRIMING_REPORT_FILE, $primingReport );

			$updateResult = $this->runPluginUpdate( $rootDir, $envOverrides, $artifacts );
			$summary[ 'update_result' ] = $updateResult;
			$artifacts->writeJson( PublicUpgradeArtifacts::UPDATE_RESULT_FILE, $updateResult );
			$this->assertPluginUpdateResult( $updateResult, $publicVersion, $metadata->version() );

			$this->runtime->runDueCron( $rootDir, $envOverrides, $artifacts );

			$finalVersion = $this->runtime->assertPluginVersion(
				$rootDir,
				$envOverrides,
				$artifacts,
				self::PLUGIN_SLUG,
				$metadata->version(),
				'Read final Shield version',
				'Final Shield version'
			);
			$summary[ 'final_version' ] = $finalVersion;

			$this->runtime->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, false, true );
			$runtimeArtifactsCollected = true;
			$summary[ 'log_findings' ] = $this->runtime->scanArtifacts( $artifacts, false );
			if ( $summary[ 'log_findings' ] !== [] ) {
				throw new PublicUpgradeTestFailureException( 'Upgrade logs contain fatal or Shield-scoped error output.' );
			}

			$summary[ 'status' ] = 'pass';
			$exitCode = self::EXIT_PASS;
			return $exitCode;
		}
		catch ( PublicUpgradeVersionGateException $e ) {
			$summary[ 'status' ] = 'version-gate';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_VERSION_GATE;
			echo 'Upgrade public version gate: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( PackageRuntimeTestFailureException $e ) {
			$summary[ 'status' ] = 'fail';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_TEST_FAILURE;
			echo 'Upgrade public test failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( \Throwable $e ) {
			$summary[ 'status' ] = 'setup-error';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_SETUP_FAILURE;
			echo 'Upgrade public setup failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		finally {
			$this->runtime->finishRun(
				$rootDir,
				$envOverrides,
				$artifacts,
				$showDockerOutput,
				$exitCode,
				$runtimeArtifactsCollected,
				$summary
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function installPublicPlugin(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts
	) :void {
		$this->runtime->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'install', self::PLUGIN_SLUG, '--activate' ],
			'Install latest public Shield from WordPress.org',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function writeUpdateConfig(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		$config = [
			'plugin'      => self::PLUGIN_FILE,
			'slug'        => self::PLUGIN_SLUG,
			'id'          => self::PLUGIN_SLUG,
			'new_version' => $metadata->version(),
			'package'     => $this->runtime->internalPackageUrl(),
			'url'         => 'https://wordpress.org/plugins/'.self::PLUGIN_SLUG.'/',
		];
		$this->runtime->runJsonFixture(
			$rootDir,
			$envOverrides,
			$artifacts,
			$this->fixture( 'write-update-config.php' ),
			[ \base64_encode( \json_encode( $config, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) ) ]
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function verifyPackageUrl( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$result = $this->runtime->runJsonFixture(
			$rootDir,
			$envOverrides,
			$artifacts,
			$this->fixture( 'verify-package-url.php' )
		);
		if ( !( $result[ 'ok' ] ?? false ) ) {
			throw new PublicUpgradeTestFailureException(
				'Package URL is not reachable from WordPress: '.(string)( $result[ 'message' ] ?? 'unknown error' )
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @return array<string,mixed>
	 */
	private function runPluginUpdate( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :array {
		$process = $this->runtime->runWpAllowFailure(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'update', self::PLUGIN_SLUG, '--format=json' ],
			'Run normal WordPress plugin update'
		);
		$output = \trim( $process->getOutput() );
		$decoded = \json_decode( $output, true );
		if ( !\is_array( $decoded ) ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update did not return valid JSON.' );
		}

		if ( $this->isList( $decoded ) ) {
			$row = $decoded[ 0 ] ?? null;
			if ( !\is_array( $row ) ) {
				throw new PublicUpgradeTestFailureException( 'Plugin update JSON did not contain a result row.' );
			}
			$result = $row;
		}
		else {
			$result = $decoded;
		}

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new PublicUpgradeTestFailureException(
				'WordPress plugin update failed with status '.(string)( $result[ 'status' ] ?? 'missing' ).'.'
			);
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $publicInfo
	 */
	private function assertPublicVersionMatchesMetadata( string $publicVersion, array $publicInfo ) :void {
		$expectedPublicVersion = (string)( $publicInfo[ 'version' ] ?? '' );
		if ( $expectedPublicVersion === '' ) {
			throw new \RuntimeException( 'WordPress.org plugin metadata did not include a version.' );
		}
		if ( $publicVersion !== $expectedPublicVersion ) {
			throw new PublicUpgradeTestFailureException(
				'Installed public Shield version '.$publicVersion.' does not match WordPress.org version '.$expectedPublicVersion.'.'
			);
		}
	}

	/**
	 * @param array<mixed> $value
	 */
	private function isList( array $value ) :bool {
		return $value === [] || \array_keys( $value ) === \range( 0, \count( $value ) - 1 );
	}

	/**
	 * @param array<string,mixed> $result
	 */
	private function assertPluginUpdateResult( array $result, string $publicVersion, string $packageVersion ) :void {
		if ( (string)( $result[ 'status' ] ?? '' ) !== 'Updated' ) {
			throw new PublicUpgradeTestFailureException(
				'Plugin update status was '.(string)( $result[ 'status' ] ?? 'missing' ).', expected Updated.'
			);
		}
		if ( (string)( $result[ 'old_version' ] ?? '' ) !== $publicVersion ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update old_version did not match public version.' );
		}
		if ( (string)( $result[ 'new_version' ] ?? '' ) !== $packageVersion ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update new_version did not match package version.' );
		}
	}

	private function fixture( string $file ) :string {
		return self::FIXTURE_DIR.$file;
	}
}

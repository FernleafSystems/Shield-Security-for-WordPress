<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class PopularPluginsCompatibilityTestLane {

	public const EXIT_PASS = 0;
	public const EXIT_TEST_FAILURE = 1;
	public const EXIT_SETUP_FAILURE = 2;
	public const EXIT_BASELINE_FAILURE = 4;

	private const EXPECTED_PLUGIN_COUNT = 20;
	private const PLUGIN_SLUG = 'wp-simple-firewall';
	private const MANIFEST_PATH = 'tests/fixtures/popular-plugin-compat/plugin-slugs.json';

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
			'popular-plugins',
			'Shield Popular Plugins Compatibility Test',
			'SHIELD_POPULAR_PLUGIN_TEST_COMPOSE_PROJECT',
			'shield-popular-plugins',
			'SHIELD_POPULAR_PLUGIN_TEST_SITE_PORT',
			'8895',
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
		$artifacts = PopularPluginsCompatibilityArtifacts::resolve( $rootDir, $artifactDir, $showDockerOutput );
		$artifacts->resetForRun();

		$summary = $artifacts->baseSummary( self::EXIT_SETUP_FAILURE );
		$envOverrides = null;
		$exitCode = self::EXIT_SETUP_FAILURE;
		$runtimeArtifactsCollected = false;

		try {
			echo 'Mode: popular-plugins'.\PHP_EOL;
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

			$this->runtime->prepareCleanSite( $rootDir, $envOverrides, $artifacts, $showDockerOutput, false );

			$pluginSlugs = $this->loadPluginSlugs( $rootDir );
			$summary[ 'companion_plugin_count' ] = \count( $pluginSlugs );
			$artifacts->writeJson( PopularPluginsCompatibilityArtifacts::COMPANION_PLUGINS_FILE, [
				'count' => \count( $pluginSlugs ),
				'slugs' => $pluginSlugs,
			] );

			$activationResults = $this->installAndActivateCompanionPlugins(
				$rootDir,
				$envOverrides,
				$artifacts,
				$pluginSlugs
			);
			$summary[ 'activation_results' ] = $activationResults;

			$this->runtime->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, false, true );
			$summary[ 'baseline_log_findings' ] = $this->runtime->scanArtifacts( $artifacts, false );
			if ( $this->runtime->hasGlobalFatalFinding( $summary[ 'baseline_log_findings' ] ) ) {
				throw new PopularPluginsBaselineFailureException( 'Popular plugin baseline logs contain fatal output before Shield activation.' );
			}

			$this->runtime->publishPackageZip( $rootDir, $envOverrides, $artifacts, $metadata );
			$this->installShieldPackage( $rootDir, $envOverrides, $artifacts );
			$this->assertShieldVersion( $rootDir, $envOverrides, $artifacts, $metadata->version() );

			$activePlugins = $this->runtime->runJsonWpCommand(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'plugin', 'list', '--status=active', '--format=json' ],
				'List active plugins after Shield activation'
			);
			$summary[ 'active_plugins' ] = $activePlugins;

			$this->runtime->runWp(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'eval', 'echo "shield-popular-plugins-bootstrap-ok";' ],
				'Run post-activation bootstrap probe',
				true
			);
			$this->runtime->runDueCron( $rootDir, $envOverrides, $artifacts );

			$this->runtime->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, false, true );
			$runtimeArtifactsCollected = true;
			$summary[ 'log_findings' ] = $this->runtime->scanArtifacts( $artifacts, false );
			if ( $summary[ 'log_findings' ] !== [] ) {
				throw new PackageRuntimeTestFailureException( 'Popular plugin compatibility logs contain fatal or Shield-scoped error output.' );
			}

			$summary[ 'status' ] = 'pass';
			$exitCode = self::EXIT_PASS;
			return $exitCode;
		}
		catch ( PopularPluginsBaselineFailureException $e ) {
			$summary[ 'status' ] = 'baseline-fail';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_BASELINE_FAILURE;
			echo 'Popular plugin baseline failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( PackageRuntimeTestFailureException $e ) {
			$summary[ 'status' ] = 'fail';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_TEST_FAILURE;
			echo 'Popular plugin compatibility test failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( \Throwable $e ) {
			$summary[ 'status' ] = 'setup-error';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_SETUP_FAILURE;
			echo 'Popular plugin compatibility setup failed: '.$e->getMessage().\PHP_EOL;
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
	 * @return string[]
	 */
	private function loadPluginSlugs( string $rootDir ) :array {
		$path = Path::join( $rootDir, self::MANIFEST_PATH );
		$decoded = \json_decode( (string)\file_get_contents( $path ), true );
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException( 'Popular plugin manifest is not valid JSON.' );
		}

		$slugs = $decoded[ 'slugs' ] ?? $decoded;
		if ( !\is_array( $slugs ) ) {
			throw new \RuntimeException( 'Popular plugin manifest must contain a slugs array.' );
		}

		$slugs = \array_values( \array_filter( \array_map(
			static fn( $slug ) :string => \is_string( $slug ) ? \trim( $slug ) : '',
			$slugs
		) ) );
		if ( \count( $slugs ) !== self::EXPECTED_PLUGIN_COUNT || \count( \array_unique( $slugs ) ) !== self::EXPECTED_PLUGIN_COUNT ) {
			throw new \RuntimeException( 'Popular plugin manifest must contain exactly '.self::EXPECTED_PLUGIN_COUNT.' unique slugs.' );
		}

		return $slugs;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[]                   $pluginSlugs
	 * @return array<int,array{slug:string,install_status:int,activate_status:int,install_output:string,install_error:string,activate_output:string,activate_error:string}>
	 */
	private function installAndActivateCompanionPlugins(
		string $rootDir,
		array $envOverrides,
		PopularPluginsCompatibilityArtifacts $artifacts,
		array $pluginSlugs
	) :array {
		$results = [];
		foreach ( $pluginSlugs as $slug ) {
			$install = $this->runtime->runWpAllowFailure(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'plugin', 'install', $slug ],
				'Install companion plugin '.$slug
			);
			$installStatus = $install->getExitCode() ?? 1;
			if ( $installStatus !== 0 ) {
				$results[] = [
					'slug'            => $slug,
					'install_status'  => $installStatus,
					'activate_status' => -1,
					'install_output'  => $this->trimProcessOutput( $install->getOutput() ),
					'install_error'   => $this->trimProcessOutput( $install->getErrorOutput() ),
					'activate_output' => '',
					'activate_error'  => '',
				];
				$artifacts->writeJson( PopularPluginsCompatibilityArtifacts::ACTIVATION_RESULTS_FILE, $results );
				throw new PopularPluginsBaselineFailureException( 'Companion plugin install failed for '.$slug.'.' );
			}

			$activate = $this->runtime->runWpAllowFailure(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'plugin', 'activate', $slug ],
				'Activate companion plugin '.$slug
			);
			$activateStatus = $activate->getExitCode() ?? 1;
			$results[] = [
				'slug'            => $slug,
				'install_status'  => $installStatus,
				'activate_status' => $activateStatus,
				'install_output'  => $this->trimProcessOutput( $install->getOutput() ),
				'install_error'   => $this->trimProcessOutput( $install->getErrorOutput() ),
				'activate_output' => $this->trimProcessOutput( $activate->getOutput() ),
				'activate_error'  => $this->trimProcessOutput( $activate->getErrorOutput() ),
			];
			$artifacts->writeJson( PopularPluginsCompatibilityArtifacts::ACTIVATION_RESULTS_FILE, $results );
			if ( $activateStatus !== 0 ) {
				throw new PopularPluginsBaselineFailureException( 'Companion plugin activation failed for '.$slug.'.' );
			}
		}

		return $results;
	}

	private function trimProcessOutput( string $output ) :string {
		$output = \trim( $output );
		if ( \strlen( $output ) <= 4000 ) {
			return $output;
		}
		return \substr( $output, 0, 4000 )."\n[truncated]";
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function installShieldPackage(
		string $rootDir,
		array $envOverrides,
		PopularPluginsCompatibilityArtifacts $artifacts
	) :void {
		$this->runtime->installPublishedPackageZip(
			$rootDir,
			$envOverrides,
			$artifacts,
			'Install current Shield package alongside popular plugins'
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function assertShieldVersion(
		string $rootDir,
		array $envOverrides,
		PopularPluginsCompatibilityArtifacts $artifacts,
		string $packageVersion
	) :void {
		$this->runtime->assertPluginVersion(
			$rootDir,
			$envOverrides,
			$artifacts,
			self::PLUGIN_SLUG,
			$packageVersion,
			'Read installed Shield package version',
			'Final Shield version'
		);
	}
}

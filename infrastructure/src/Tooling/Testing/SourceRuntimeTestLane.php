<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class SourceRuntimeTestLane {

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	public function run(
		string $rootDir,
		bool $refreshSetup = false,
		bool $showDockerOutput = false,
		bool $skipUnitTests = false,
		bool $includePreviousWp = false
	) :int {
		echo 'Mode: source'.\PHP_EOL;

		$originalShieldPackagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		$hasOriginalShieldPackagePath = \is_string( $originalShieldPackagePath );
		\putenv( 'SHIELD_PACKAGE_PATH' );

		try {
			$logSink = SourceRuntimeLogSink::createFromEnvironment();
			$overallExitCode = 0;
			$runId = $this->buildRunId();
			$transientExpiresAt = \gmdate( \DATE_ATOM, \time() + 6*60*60 );
			$reusableExpiresAt = \gmdate( \DATE_ATOM, \time() + 30*24*60*60 );
			$dockerEnvPath = Path::join( $rootDir, 'tests', 'docker', '.env' );
			$preflightCallback = $logSink !== null
				? $logSink->callbackForPhase( 'preflight', 'Prepare source runtime environment' )
				: null;
			try {
				$this->environmentResolver->assertDockerReady( $rootDir );

				$phpVersion = $this->environmentResolver->resolvePhpVersion( $rootDir );
				[ $latestWpVersion, $previousWpVersion ] = $this->environmentResolver->detectWordpressVersions( $rootDir );

				$this->environmentResolver->writeDockerEnvFile(
					$dockerEnvPath,
					$this->buildDockerEnvLines( $phpVersion, $latestWpVersion, $previousWpVersion )
				);
				if ( $logSink !== null ) {
					$logSink->finishPhase( 'preflight', 0 );
				}
			}
			catch ( \Throwable $throwable ) {
				if ( \is_file( $dockerEnvPath ) ) {
					\unlink( $dockerEnvPath );
				}
				if ( $logSink !== null && $preflightCallback !== null ) {
					$preflightCallback( Process::OUT, 'Exception: '.$throwable->getMessage().\PHP_EOL );
					$logSink->finishPhase( 'preflight', 1 );
					$logSink->writeStepSummary( 1 );
				}
				throw $throwable;
			}

			$composeFiles = $this->buildComposeFiles();
			$dockerProcessEnvOverrides = \array_merge(
				$this->environmentResolver->buildDockerProcessEnvOverrides(
					'shield-tests',
					true
				),
				DockerCleanupPolicy::source()->labelEnvironment(
					$runId,
					DockerHarnessLabels::LIFECYCLE_TRANSIENT,
					'source',
					$transientExpiresAt,
					$runId,
					DockerHarnessLabels::LIFECYCLE_REUSABLE,
					$reusableExpiresAt
				)
			);
			try {
				echo 'Starting source-runtime Docker checks on working tree.'.\PHP_EOL;
				$this->dockerComposeExecutor->runIgnoringFailure(
					$rootDir,
					$composeFiles,
					$this->buildComposeCleanupCommand(),
					$dockerProcessEnvOverrides,
					$showDockerOutput
				);

				if ( $this->runComposePhase(
					'mysql-up',
					'Start MySQL services',
					$rootDir,
					$composeFiles,
					$this->buildComposeMysqlUpCommand( $includePreviousWp ),
					$showDockerOutput,
					$dockerProcessEnvOverrides,
					$logSink
				) !== 0 ) {
					$overallExitCode = 1;
					return 1;
				}
				if ( $this->runComposePhase(
					'build-runners',
					'Build Docker test runners',
					$rootDir,
					$composeFiles,
					$this->buildComposeBuildRunnersCommand( $includePreviousWp ),
					$showDockerOutput,
					$dockerProcessEnvOverrides,
					$logSink
				) !== 0 ) {
					$overallExitCode = 1;
					return 1;
				}
				if ( $this->runSourceSetupOnce(
					$rootDir,
					$phpVersion,
					$refreshSetup,
					$showDockerOutput,
					$dockerProcessEnvOverrides,
					$logSink,
					$runId,
					$transientExpiresAt,
					$reusableExpiresAt
				) !== 0 ) {
					$overallExitCode = 1;
					return 1;
				}

				if ( $this->runComposePhase(
					'runtime-latest',
					'Run latest WordPress runtime checks',
					$rootDir,
					$composeFiles,
					$this->buildComposeRunLatestCommand( $skipUnitTests ),
					$showDockerOutput,
					$dockerProcessEnvOverrides,
					$logSink
				) !== 0 ) {
					$overallExitCode = 1;
				}
				if ( $includePreviousWp ) {
					if ( $this->runComposePhase(
						'runtime-previous',
						'Run previous WordPress runtime checks',
						$rootDir,
						$composeFiles,
						$this->buildComposeRunPreviousCommand( $skipUnitTests ),
						$showDockerOutput,
						$dockerProcessEnvOverrides,
						$logSink
					) !== 0 ) {
						$overallExitCode = 1;
					}
				}

				return $overallExitCode;
			}
			catch ( \Throwable $throwable ) {
				$overallExitCode = 1;
				throw $throwable;
			}
			finally {
				$this->dockerComposeExecutor->runIgnoringFailure(
					$rootDir,
					$composeFiles,
					$this->buildComposeCleanupCommand(),
					$dockerProcessEnvOverrides,
					$showDockerOutput
				);
				if ( \is_file( $dockerEnvPath ) ) {
					\unlink( $dockerEnvPath );
				}
				if ( $logSink !== null ) {
					$logSink->writeStepSummary( $overallExitCode );
				}
			}
		}
		finally {
			if ( $hasOriginalShieldPackagePath ) {
				\putenv( 'SHIELD_PACKAGE_PATH='.$originalShieldPackagePath );
			}
			else {
				\putenv( 'SHIELD_PACKAGE_PATH' );
			}
		}
	}

	/**
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runSourceSetupOnce(
		string $rootDir,
		string $phpVersion,
		bool $refreshSetup = false,
		bool $showDockerOutput = false,
		?array $envOverrides = null,
		?SourceRuntimeLogSink $logSink = null,
		string $runId = 'source',
		string $transientExpiresAt = '',
		string $reusableExpiresAt = ''
	) :int {
		echo 'Preparing source mode test setup once before runtime checks.'.\PHP_EOL;

		if ( $refreshSetup ) {
			echo 'Refreshing source setup cache state.'.\PHP_EOL;
			$this->setupCacheCoordinator->clearState( $rootDir );
			$this->purgeNodeModulesVolume(
				$rootDir,
				$this->setupCacheCoordinator->getNodeModulesVolumeName( $rootDir ),
				$envOverrides
			);
		}

		$setup = $this->setupCacheCoordinator->evaluateRuntimeSetup( $rootDir, $phpVersion, $refreshSetup );
		$composeFiles = $this->buildComposeFiles();

		if ( $setup[ 'needs_composer_install' ] ) {
			if ( $this->runComposePhase(
				'setup-composer',
				'Source composer install setup',
				$rootDir,
				$composeFiles,
				$this->buildSourceComposerInstallSetupCommand(),
				$showDockerOutput,
				$envOverrides,
				$logSink
			) !== 0 ) {
				return 1;
			}
		}
		else {
			echo 'Skipping composer install setup (cache hit).'.\PHP_EOL;
		}

		if ( $setup[ 'needs_build_config' ] ) {
			if ( $this->runComposePhase(
				'setup-build-config',
				'Source build-config setup',
				$rootDir,
				$composeFiles,
				$this->buildSourceBuildConfigSetupCommand(),
				$showDockerOutput,
				$envOverrides,
				$logSink
			) !== 0 ) {
				return 1;
			}
		}
		else {
			echo 'Skipping build-config setup (cache hit).'.\PHP_EOL;
		}

		if ( $setup[ 'needs_npm_install' ] ) {
			$this->ensureNodeModulesVolume(
				$rootDir,
				$setup[ 'node_modules_volume' ],
				$runId,
				$reusableExpiresAt,
				$envOverrides
			);
			$nodeExitCode = $this->runProcessPhase(
				'setup-assets',
				'Node dependency install and asset build',
				$this->buildNodeAssetBuildCommand( $rootDir, $setup[ 'node_modules_volume' ], true, $runId, $transientExpiresAt ),
				$rootDir,
				$envOverrides,
				$logSink
			);
			if ( $nodeExitCode !== 0 ) {
				return $nodeExitCode;
			}
		}
		elseif ( $setup[ 'needs_npm_build' ] ) {
			$this->ensureNodeModulesVolume(
				$rootDir,
				$setup[ 'node_modules_volume' ],
				$runId,
				$reusableExpiresAt,
				$envOverrides
			);
			$nodeExitCode = $this->runProcessPhase(
				'setup-assets',
				'Asset build only',
				$this->buildNodeAssetBuildCommand( $rootDir, $setup[ 'node_modules_volume' ], false, $runId, $transientExpiresAt ),
				$rootDir,
				$envOverrides,
				$logSink
			);
			if ( $nodeExitCode !== 0 ) {
				return $nodeExitCode;
			}
		}
		else {
			echo 'Skipping node install/build setup (cache hit).'.\PHP_EOL;
		}

		$this->setupCacheCoordinator->persistRuntimeSetupState( $rootDir, $setup[ 'fingerprints' ] );
		return 0;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			'tests/docker/docker-compose.yml',
		];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeCleanupCommand() :array {
		return [ 'down', '-v', '--remove-orphans' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeMysqlUpCommand( bool $includePreviousWp ) :array {
		$command = [ 'up', '-d', '--wait', '--wait-timeout', '60', 'mysql-latest' ];
		if ( $includePreviousWp ) {
			$command[] = 'mysql-previous';
		}
		return $command;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeBuildRunnersCommand( bool $includePreviousWp ) :array {
		$command = [ 'build', 'test-runner-latest' ];
		if ( $includePreviousWp ) {
			$command[] = 'test-runner-previous';
		}
		return $command;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunLatestCommand( bool $skipUnitTests ) :array {
		return $this->buildComposeRunCommand( 'test-runner-latest', $skipUnitTests );
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunPreviousCommand( bool $skipUnitTests ) :array {
		return $this->buildComposeRunCommand( 'test-runner-previous', $skipUnitTests );
	}

	/**
	 * @return string[]
	 */
	private function buildSourceComposerInstallSetupCommand() :array {
		return [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress' ];
	}

	/**
	 * @return string[]
	 */
	private function buildSourceBuildConfigSetupCommand() :array {
		return [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'build:config' ];
	}

	/**
	 * @return string[]
	 */
	private function buildNodeAssetBuildCommand(
		string $rootDir,
		string $nodeModulesVolume,
		bool $installDependencies,
		string $runId,
		string $transientExpiresAt
	) :array {
		$command = $installDependencies
			? 'npm ci --no-audit --no-fund && npm run build'
			: 'npm run build';

		return [
			'docker',
			'run',
			'--rm',
			'--label',
			DockerHarnessLabels::HARNESS.'='.DockerCleanupPolicy::source()->harnessLabelValue(),
			'--label',
			DockerHarnessLabels::RUN_ID.'='.$runId,
			'--label',
			DockerHarnessLabels::LANE.'=source-node',
			'--label',
			DockerHarnessLabels::LIFECYCLE.'='.DockerHarnessLabels::LIFECYCLE_TRANSIENT,
			'--label',
			DockerHarnessLabels::EXPIRES_AT.'='.$transientExpiresAt,
			'-v',
			$rootDir.':/app',
			'-v',
			$nodeModulesVolume.':/app/node_modules',
			'-w',
			'/app',
			$this->setupCacheCoordinator->getNodeImageTag(),
			'sh',
			'-c',
			$command,
		];
	}

	/**
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function purgeNodeModulesVolume(
		string $rootDir,
		string $nodeModulesVolume,
		?array $envOverrides = null
	) :void {
		$process = $this->processRunner->run(
			[
				'docker',
				'volume',
				'rm',
				'-f',
				$nodeModulesVolume,
			],
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$stderr = \trim( $process->getErrorOutput() );
			throw new \RuntimeException(
				'Failed to purge source node_modules volume before refresh: '.$nodeModulesVolume.
				( $stderr === '' ? '' : ' STDERR: '.$stderr )
			);
		}
	}

	/**
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function ensureNodeModulesVolume(
		string $rootDir,
		string $nodeModulesVolume,
		string $runId,
		string $expiresAt,
		?array $envOverrides = null
	) :void {
		$command = [
			'docker',
			'volume',
			'create',
			'--label',
			DockerHarnessLabels::HARNESS.'='.DockerCleanupPolicy::source()->harnessLabelValue(),
			'--label',
			DockerHarnessLabels::RUN_ID.'='.$runId,
			'--label',
			DockerHarnessLabels::LANE.'=source-node',
			'--label',
			DockerHarnessLabels::LIFECYCLE.'='.DockerHarnessLabels::LIFECYCLE_REUSABLE,
			'--label',
			DockerHarnessLabels::EXPIRES_AT.'='.$expiresAt,
			$nodeModulesVolume,
		];
		$process = $this->processRunner->run(
			$command,
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( 'Failed to create labeled source node_modules volume: '.$nodeModulesVolume );
		}
	}

	private function buildRunId() :string {
		return 'shield-plugin-source-'.\gmdate( 'YmdHis' ).'-'.\bin2hex( \random_bytes( 4 ) );
	}

	/**
	 * @return string[]
	 */
	private function buildDockerEnvLines( string $phpVersion, string $latestWpVersion, string $previousWpVersion ) :array {
		$lines = [
			'PHP_VERSION='.$phpVersion,
			'WP_VERSION_LATEST='.$latestWpVersion,
			'WP_VERSION_PREVIOUS='.$previousWpVersion,
			'TEST_PHP_VERSION='.$phpVersion,
			'SHIELD_TEST_MODE=source',
		];

		foreach ( [ 'PHPUNIT_DEBUG', 'SHIELD_TEST_VERBOSE' ] as $optionalEnvVar ) {
			$value = \getenv( $optionalEnvVar );
			if ( \is_string( $value ) && $value !== '' ) {
				$lines[] = $optionalEnvVar.'='.$value;
			}
		}

		return $lines;
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runProcessPhase(
		string $phaseKey,
		string $label,
		array $command,
		string $rootDir,
		?array $envOverrides = null,
		?SourceRuntimeLogSink $logSink = null
	) :int {
		echo 'Phase: '.$label.'.'.\PHP_EOL;
		$callback = $logSink !== null ? $logSink->callbackForPhase( $phaseKey, $label ) : null;
		$exitCode = $this->processRunner->runForExitCode(
			$command,
			$rootDir,
			$callback,
			$envOverrides
		);
		if ( $logSink !== null ) {
			$logSink->finishPhase( $phaseKey, $exitCode );
		}
		echo ( $exitCode === 0 ? 'Phase complete: ' : 'Phase failed: ' ).$label.'.'.\PHP_EOL;
		return $exitCode;
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runComposePhase(
		string $phaseKey,
		string $label,
		string $rootDir,
		array $composeFiles,
		array $subCommand,
		bool $showDockerOutput,
		?array $envOverrides = null,
		?SourceRuntimeLogSink $logSink = null
	) :int {
		echo 'Phase: '.$label.'.'.\PHP_EOL;
		$callback = $logSink !== null ? $logSink->callbackForPhase( $phaseKey, $label ) : null;
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			$subCommand,
			$envOverrides,
			$callback,
			$showDockerOutput
		);
		if ( $logSink !== null ) {
			$logSink->finishPhase( $phaseKey, $exitCode );
		}
		echo ( $exitCode === 0 ? 'Phase complete: ' : 'Phase failed: ' ).$label.'.'.\PHP_EOL;
		return $exitCode;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeRunCommand( string $serviceName, bool $skipUnitTests ) :array {
		$command = [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1' ];
		$skipUnitTestsEnv = $skipUnitTests ? '1' : \getenv( 'SHIELD_SKIP_UNIT_TESTS' );
		if ( \is_string( $skipUnitTestsEnv ) && $skipUnitTestsEnv !== '' ) {
			$command[] = '-e';
			$command[] = 'SHIELD_SKIP_UNIT_TESTS='.$skipUnitTestsEnv;
		}

		$command[] = $serviceName;
		return $command;
	}
}

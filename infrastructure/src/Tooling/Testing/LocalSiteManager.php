<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class LocalSiteManager {

	private const DB_SERVICE_NAME = 'db';
	private const WORDPRESS_SERVICE_NAME = 'wordpress';
	private const WPCLI_SERVICE_NAME = 'wp-cli';
	private const DB_ROOT_PASSWORD = 'testpass';
	private const BROWSER_FIXTURE_ENDPOINT_SOURCE = 'tests/browser/support/shield-browser-fixtures.php';
	private const BROWSER_FIXTURE_ENDPOINT_TARGET = '/var/www/html/wp-content/mu-plugins/shield-browser-fixtures.php';
	private const BROWSER_FIXTURE_TOKEN_FILE = '/var/www/html/wp-content/.shield-browser-fixture-token';
	private const BROWSER_LANE_READY_MARKER = '/var/www/html/wp-content/.shield-browser-lane-ready.json';
	private const BROWSER_LANE_READY_SCHEMA_VERSION = 2;

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalSiteProbe $probe;

	private LocalSiteRuntimeRefresher $runtimeRefresher;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	private LocalSiteDefinition $definition;

	public function __construct(
		LocalSiteDefinition $definition,
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteProbe $probe = null,
		?LocalSiteRuntimeRefresher $runtimeRefresher = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->definition = $definition;
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->probe = $probe ?? new LocalSiteProbe();
		$this->runtimeRefresher = $runtimeRefresher ?? new LocalSiteRuntimeRefresher( $this->processRunner );
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	public function definition() :LocalSiteDefinition {
		return $this->definition;
	}

	public function up( string $rootDir ) :int {
		$this->ensureReady( $rootDir, false );
		return 0;
	}

	public function down( string $rootDir ) :int {
		return $this->dockerComposeExecutor->run(
			$rootDir,
			$this->buildComposeFiles(),
			[ 'down', '--remove-orphans' ],
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
	}

	/**
	 * @param string[] $wpCliArgs
	 */
	public function wp( string $rootDir, array $wpCliArgs ) :int {
		$this->runPreflightChecks( $rootDir, false );
		$this->ensureReadyAfterPreflight( $rootDir, null );

		return $this->processRunner->runForExitCode(
			$this->buildWpCliCommand( $wpCliArgs ),
			$rootDir,
			null,
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return array{stdout:string,stderr:string}
	 */
	public function wpCapture( string $rootDir, array $wpCliArgs ) :array {
		$this->runPreflightChecks( $rootDir, false );

		$stdout = '';
		$stderr = '';
		$preflightCollector = static function ( string $type, string $buffer ) use ( &$stderr ) :void {
			$stderr .= $buffer;
		};
		$collector = static function ( string $type, string $buffer ) use ( &$stdout, &$stderr ) :void {
			if ( $type === Process::ERR ) {
				$stderr .= $buffer;
			}
			else {
				$stdout .= $buffer;
			}
		};

		$this->ensureReadyAfterPreflight( $rootDir, $preflightCollector );

		$process = $this->processRunner->run(
			$this->buildWpCliCommand( $wpCliArgs ),
			$rootDir,
			$collector,
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			$message = \sprintf( 'WP-CLI command failed with exit code %d.', $exitCode );
			$errorOutput = \trim( $stderr );
			if ( $errorOutput !== '' ) {
				$message .= ' '.$errorOutput;
			}
			throw new \RuntimeException( $message );
		}

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
		];
	}

	public function reset( string $rootDir, bool $requirePlaywright = false, ?callable $onOutput = null ) :int {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );

		if ( $this->definition->usesSharedDatabase() ) {
			$this->ensureSharedDatabaseReady( $rootDir, $onOutput );
		}

		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$this->buildComposeFiles(),
			[ 'down', '-v', '--remove-orphans' ],
			$this->buildRuntimeEnvOverrides( $rootDir ),
			$onOutput
		);
		if ( $exitCode !== 0 ) {
			throw new \RuntimeException( $this->diagnoseCommandFailure(
				'Browser lane reset failed while removing lane WordPress containers and volumes.',
				$this->buildComposeCommandForExecution( $this->buildComposeFiles(), [ 'down', '-v', '--remove-orphans' ] ),
				$exitCode
			) );
		}
		if ( $this->definition->usesSharedDatabase() ) {
			$this->resetSharedDatabase( $rootDir );
		}
		$this->ensureReadyAfterPreflight( $rootDir, $onOutput, $this->definition->usesSharedDatabase() );
		return 0;
	}

	public function prepareBrowserLane(
		string $rootDir,
		string $mode,
		bool $requirePlaywright,
		string $fixtureToken,
		?callable $onOutput = null
	) :int {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );
		if ( $this->definition->usesSharedDatabase() ) {
			$this->ensureSharedDatabaseReady( $rootDir, $onOutput );
		}

		if ( $mode === 'clean' ) {
			$exitCode = $this->dockerComposeExecutor->run(
				$rootDir,
				$this->buildComposeFiles(),
				[ 'down', '-v', '--remove-orphans' ],
				$this->buildRuntimeEnvOverrides( $rootDir ),
				$onOutput
			);
			if ( $exitCode !== 0 ) {
				throw new \RuntimeException( $this->diagnoseCommandFailure(
					'Browser lane reset failed while removing lane WordPress containers and volumes.',
					$this->buildComposeCommandForExecution( $this->buildComposeFiles(), [ 'down', '-v', '--remove-orphans' ] ),
					$exitCode
				) );
			}
			if ( $this->definition->usesSharedDatabase() ) {
				$this->resetSharedDatabase( $rootDir );
			}
			$this->ensureReadyAfterPreflight( $rootDir, $onOutput, true, $fixtureToken, true );
			return 0;
		}

		if ( $mode !== 'warm' ) {
			throw new \InvalidArgumentException( 'Browser lane mode must be "clean" or "warm".' );
		}

		$this->ensureReadyAfterPreflight( $rootDir, $onOutput, true, $fixtureToken, false );
		return 0;
	}

	/**
	 * @return array{site_url:string,site_healthy:bool,port_open:bool,admin_user:string,lane_key:string,compose_project:string,db_name:string}
	 */
	public function status( string $rootDir ) :array {
		$this->environmentResolver->assertDockerReady( $rootDir );

		return [
			'site_url' => $this->definition->siteUrl(),
			'site_healthy' => $this->isSiteHealthy(),
			'port_open' => $this->probe->isTcpPortOpen( $this->definition->siteHost(), $this->definition->sitePort() ),
			'admin_user' => $this->definition->adminUser(),
			'lane_key' => $this->definition->key(),
			'compose_project' => $this->definition->composeProjectName(),
			'db_name' => $this->definition->dbName(),
		];
	}

	public function ensureReady( string $rootDir, bool $requirePlaywright ) :void {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );
		$this->ensureReadyAfterPreflight( $rootDir );
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function ensureReadyAfterPreflight(
		string $rootDir,
		?callable $onOutput = null,
		bool $sharedDatabaseAlreadyReady = false,
		?string $fixtureToken = null,
		bool $forceProvision = true
	) :void {
		if ( $this->definition->usesSharedDatabase() && !$sharedDatabaseAlreadyReady ) {
			$this->ensureSharedDatabaseReady( $rootDir, $onOutput );
		}

		$envOverrides = $this->buildRuntimeEnvOverrides( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$containerId = $this->resolveOrStartWordpressContainer( $rootDir, $composeFiles, $envOverrides, $onOutput );

		$this->refreshRuntimeAndAssertHealthy( $rootDir, $containerId, $onOutput );
		if ( $fixtureToken !== null ) {
			$this->installBrowserFixtureEndpoint( $rootDir, $containerId, $fixtureToken, $onOutput );
		}
		if ( !$forceProvision && $this->isBrowserLaneReady( $rootDir, $containerId ) && $this->isSiteHealthy() ) {
			return;
		}
		$this->provisionBaselineAndAssertHealthy( $rootDir, $envOverrides, $onOutput );
		if ( $fixtureToken !== null ) {
			$this->writeBrowserLaneReadyMarker( $rootDir, $containerId );
		}
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildRuntimeEnvOverrides( string $rootDir ) :array {
		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
			$this->definition->composeProjectName(),
			true
		);
		$envOverrides['PHP_VERSION'] = $this->environmentResolver->resolvePhpVersion( $rootDir );
		$envOverrides['SHIELD_LOCAL_SITE_DB_NAME'] = $this->definition->dbName();
		$envOverrides['SHIELD_LOCAL_SITE_DB_HOST'] = $this->definition->dbHost();
		$envOverrides['SHIELD_LOCAL_SITE_PORT'] = (string)$this->definition->sitePort();
		$envOverrides['SHIELD_LOCAL_SITE_PROFILE'] = $this->definition->key();
		return $envOverrides;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			$this->definition->composeFile(),
		];
	}

	/**
	 * @return string[]
	 */
	private function buildProvisionCommand() :array {
		$command = [
			'docker',
			'compose',
			'-f',
			$this->definition->composeFile(),
			'run',
			'--rm',
			'-T',
		];
		foreach ( $this->buildProvisionEnvironmentVariables() as $name => $value ) {
			$command[] = '-e';
			$command[] = $name.'='.$value;
		}
		$command = \array_merge( $command, [
			self::WPCLI_SERVICE_NAME,
			'sh',
			'/app/tests/docker/provision-local-site.sh',
		] );

		return $command;
	}

	/**
	 * @return array<string,string>
	 */
	private function buildProvisionEnvironmentVariables() :array {
		return [
			'SHIELD_LOCAL_SITE_URL' => $this->definition->siteUrl(),
			'SHIELD_LOCAL_SITE_TITLE' => $this->definition->siteTitle(),
			'SHIELD_LOCAL_SITE_PROFILE' => $this->definition->key(),
			'SHIELD_LOCAL_SITE_ADMIN_USER' => $this->definition->adminUser(),
			'SHIELD_LOCAL_SITE_ADMIN_PASSWORD' => $this->definition->adminPassword(),
			'SHIELD_LOCAL_SITE_ADMIN_EMAIL' => $this->definition->adminEmail(),
		];
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return string[]
	 */
	private function buildWpCliCommand( array $wpCliArgs ) :array {
		$command = [
			'docker',
			'compose',
			'-f',
			$this->definition->composeFile(),
			'run',
			'--rm',
			'-T',
			self::WPCLI_SERVICE_NAME,
			'wp',
		];
		$command = \array_merge( $command, $wpCliArgs );

		if ( !\in_array( '--allow-root', $wpCliArgs, true ) ) {
			$command[] = '--allow-root';
		}

		return $command;
	}

	private function runPreflightChecks( string $rootDir, bool $requirePlaywright ) :void {
		$this->environmentResolver->assertDockerReady( $rootDir );

		$checks = [
			Path::join( $rootDir, 'vendor', 'autoload.php' )
				=> "Composer dependencies are missing. Run 'composer install'.",
			Path::join( $rootDir, 'assets', 'dist' )
				=> "Compiled assets are missing. Run 'npm install --no-audit --no-fund' and 'npm run build'.",
			Path::join( $rootDir, 'icwp-wpsf.php' )
				=> 'Plugin root file icwp-wpsf.php is missing.',
		];

		if ( $requirePlaywright ) {
			$checks[ Path::join( $rootDir, 'node_modules', '@playwright', 'test', 'cli.js' ) ]
				= "Playwright is not installed. Run 'npm install --no-audit --no-fund' and 'npm run playwright:install'.";
		}

		foreach ( $checks as $path => $message ) {
			if ( !\file_exists( $path ) ) {
				throw new \RuntimeException( $message );
			}
		}

		$this->ensureGeneratedConfigReady( $rootDir );
	}

	private function ensureGeneratedConfigReady( string $rootDir ) :void {
		$setup = $this->setupCacheCoordinator->evaluateAnalyzeSetup( $rootDir );
		if ( $setup[ 'needs_build_config' ] ) {
			$process = $this->processRunner->run(
				[ \PHP_BINARY, './bin/build-config.php' ],
				$rootDir
			);
			$exitCode = $process->getExitCode() ?? 1;
			if ( $exitCode !== 0 ) {
				$errorOutput = \trim( $process->getErrorOutput() );
				throw new \RuntimeException(
					'Failed to regenerate plugin.json for local site tooling.'
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

	private function isSiteHealthy() :bool {
		return $this->probe->isHttpReady( $this->definition->siteUrl().'/wp-admin/' );
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function ensureSharedDatabaseReady( string $rootDir, ?callable $onOutput = null ) :void {
		$this->withSharedDatabaseLock( $rootDir, function () use ( $rootDir, $onOutput ) :void {
			$composeFiles = [ $this->definition->sharedDatabaseComposeFile() ];
			$envOverrides = $this->buildSharedDatabaseEnvOverrides( $rootDir );

			$exitCode = $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				[ 'up', '-d', self::DB_SERVICE_NAME ],
				$envOverrides,
				$onOutput
			);
			if ( $exitCode !== 0 ) {
				throw new \RuntimeException( $this->diagnoseCommandFailure(
					'Failed to start the shared browser MySQL service.',
					$this->buildComposeCommandForExecution( $composeFiles, [ 'up', '-d', self::DB_SERVICE_NAME ] ),
					$exitCode
				) );
			}

			$this->waitForSharedDatabaseHealthy( $rootDir, $envOverrides, $composeFiles );
		} );
	}

	private function withSharedDatabaseLock( string $rootDir, callable $callback ) :void {
		$lockDir = Path::join( $rootDir, 'tmp/browser-test-lanes' );
		if ( !\is_dir( $lockDir ) && !\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create browser lane lock directory: '.$lockDir );
		}
		$lockPath = Path::join( $lockDir, 'shared-db.lock' );
		$handle = \fopen( $lockPath, 'c+' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Failed to open shared browser DB lock file: '.$lockPath );
		}

		$startedAt = \time();
		try {
			do {
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					$callback();
					return;
				}
				\usleep( 500000 );
			} while ( \time() - $startedAt < 120 );

			throw new \RuntimeException( 'Timed out waiting for shared browser DB startup lock: '.$lockPath );
		}
		finally {
			if ( \is_resource( $handle ) ) {
				@\flock( $handle, \LOCK_UN );
				@\fclose( $handle );
			}
		}
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildSharedDatabaseEnvOverrides( string $rootDir ) :array {
		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
			$this->definition->sharedDatabaseComposeProjectName(),
			true
		);
		$envOverrides['PHP_VERSION'] = $this->environmentResolver->resolvePhpVersion( $rootDir );
		return $envOverrides;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $composeFiles
	 */
	private function waitForSharedDatabaseHealthy( string $rootDir, array $envOverrides, array $composeFiles ) :void {
		$command = \array_merge(
			$this->buildComposeCommandForExecution( $composeFiles, [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			[ 'mysqladmin', 'ping', '-h', '127.0.0.1', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '--silent' ]
		);
		$startedAt = \time();
		do {
			$process = $this->processRunner->run(
				$command,
				$rootDir,
				static function () :void {
				},
				$envOverrides
			);
			if ( ( $process->getExitCode() ?? 1 ) === 0 ) {
				return;
			}
			\usleep( 500000 );
		} while ( \time() - $startedAt < 60 );

		throw new \RuntimeException( $this->diagnoseCommandFailure(
			'Shared browser MySQL did not become healthy within 60 seconds.',
			$command,
			$process->getExitCode() ?? 1,
			$process->getOutput(),
			$process->getErrorOutput()
		) );
	}

	private function resetSharedDatabase( string $rootDir ) :void {
		$dbName = $this->definition->dbName();
		if ( \preg_match( '/^[a-z0-9_]+$/', $dbName ) !== 1 ) {
			throw new \RuntimeException( 'Unsafe browser lane database name: '.$dbName );
		}

		$composeFiles = [ $this->definition->sharedDatabaseComposeFile() ];
		$envOverrides = $this->buildSharedDatabaseEnvOverrides( $rootDir );
		$sql = \sprintf(
			'DROP DATABASE IF EXISTS `%1$s`; CREATE DATABASE `%1$s`;',
			$dbName
		);
		$command = \array_merge(
			$this->buildComposeCommandForExecution( $composeFiles, [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			[ 'mysql', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '-e', $sql ]
		);
		$process = $this->processRunner->run(
			$command,
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( $this->diagnoseCommandFailure(
				'Failed to recreate browser lane database '.$dbName.'.',
				$command,
				$process->getExitCode() ?? 1,
				$process->getOutput(),
				$process->getErrorOutput()
			) );
		}
	}

	/**
	 * @param string[] $composeFiles
	 * @param array<string,string|false> $envOverrides
	 */
	private function resolveOrStartWordpressContainer(
		string $rootDir,
		array $composeFiles,
		array $envOverrides,
		?callable $onOutput = null
	) :string {
		$containerId = $this->runtimeRefresher->resolveServiceContainerId(
			$rootDir,
			$composeFiles,
			self::WORDPRESS_SERVICE_NAME,
			$envOverrides
		);
		if ( $containerId !== '' ) {
			if ( !$this->isSiteHealthy() ) {
				throw new \RuntimeException(
					$this->definition->label().' is already running but unhealthy before runtime refresh. '
					.'URL: '.$this->definition->siteUrl().'/wp-admin/. '
					.'Port: '.$this->definition->sitePort().'. '
					.'Compose project: '.$this->definition->composeProjectName().'.'
				);
			}

			return $containerId;
		}

		$this->assertSitePortIsAvailable();
		$this->startDockerServices( $rootDir, $composeFiles, $envOverrides, $onOutput );
		$this->waitForWordpressStartup();

		$containerId = $this->runtimeRefresher->resolveServiceContainerId(
			$rootDir,
			$composeFiles,
			self::WORDPRESS_SERVICE_NAME,
			$envOverrides
		);
		if ( $containerId === '' ) {
			throw new \RuntimeException( $this->definition->label().' WordPress container did not resolve after startup.' );
		}

		return $containerId;
	}

	private function assertSitePortIsAvailable() :void {
		if ( $this->probe->isTcpPortOpen( $this->definition->siteHost(), $this->definition->sitePort() ) ) {
			throw new \RuntimeException(
				sprintf(
					'Port %d is already in use, but %s is not responding at %s.',
					$this->definition->sitePort(),
					$this->definition->label(),
					$this->definition->siteUrl()
				)
			);
		}
	}

	/**
	 * @param string[] $composeFiles
	 * @param array<string,string|false> $envOverrides
	 */
	private function startDockerServices(
		string $rootDir,
		array $composeFiles,
		array $envOverrides,
		?callable $onOutput = null
	) :void {
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			\array_merge(
				[ 'up', '-d' ],
				$this->definition->usesSharedDatabase()
					? [ self::WORDPRESS_SERVICE_NAME ]
					: [ self::DB_SERVICE_NAME, self::WORDPRESS_SERVICE_NAME ]
			),
			$envOverrides,
			$onOutput
		);
		if ( $exitCode !== 0 ) {
			throw new \RuntimeException( $this->diagnoseCommandFailure(
				'Failed to start the '.$this->definition->label().' Docker services.',
				$this->buildComposeCommandForExecution(
					$composeFiles,
					\array_merge(
						[ 'up', '-d' ],
						$this->definition->usesSharedDatabase()
							? [ self::WORDPRESS_SERVICE_NAME ]
							: [ self::DB_SERVICE_NAME, self::WORDPRESS_SERVICE_NAME ]
					)
				),
				$exitCode
			) );
		}
	}

	private function waitForWordpressStartup() :void {
		if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 90 ) ) {
			throw new \RuntimeException(
				$this->definition->label().' did not serve wp-login.php within 90 seconds. '
				.'URL: '.$this->definition->siteUrl().'/wp-login.php. '
				.'Port: '.$this->definition->sitePort().'. '
				.'Compose project: '.$this->definition->composeProjectName().'.'
			);
		}
	}

	private function refreshRuntimeAndAssertHealthy(
		string $rootDir,
		string $containerId,
		?callable $onOutput = null
	) :void {
		$this->runtimeRefresher->refresh( $rootDir, $containerId, $onOutput );
		if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 30 ) ) {
			throw new \RuntimeException(
				$this->definition->label().' is unhealthy after runtime refresh. '
				.'URL: '.$this->definition->siteUrl().'/wp-login.php. '
				.'Port: '.$this->definition->sitePort().'. '
				.'Compose project: '.$this->definition->composeProjectName().'.'
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function provisionBaselineAndAssertHealthy(
		string $rootDir,
		array $envOverrides,
		?callable $onOutput = null
	) :void {
		if ( $this->processRunner->runForExitCode(
			$this->buildProvisionCommand(),
			$rootDir,
			$onOutput,
			$envOverrides
		) !== 0 ) {
			throw new \RuntimeException( 'Failed to provision the '.$this->definition->label().' baseline.' );
		}

		if ( !$this->isSiteHealthy() ) {
			throw new \RuntimeException(
				$this->definition->label().' is unhealthy after provisioning. '
				.'URL: '.$this->definition->siteUrl().'/wp-admin/. '
				.'Port: '.$this->definition->sitePort().'. '
				.'Database: '.$this->definition->dbName().'. '
				.'Compose project: '.$this->definition->composeProjectName().'.'
			);
		}
	}

	private function installBrowserFixtureEndpoint(
		string $rootDir,
		string $containerId,
		string $fixtureToken,
		?callable $onOutput = null
	) :void {
		$sourcePath = Path::join( $rootDir, self::BROWSER_FIXTURE_ENDPOINT_SOURCE );
		if ( !\is_file( $sourcePath ) ) {
			throw new \RuntimeException( 'Browser fixture endpoint source is missing: '.$sourcePath );
		}

		$this->writeProgress( 'Installing browser fixture endpoint', $onOutput );
		$this->processRunner->runOrThrow(
			[
				'docker',
				'cp',
				self::BROWSER_FIXTURE_ENDPOINT_SOURCE,
				$containerId.':/tmp/shield-browser-fixtures.php',
			],
			$rootDir,
			$onOutput
		);

		$script = <<<'PHP'
$endpointSource = '/tmp/shield-browser-fixtures.php';
$endpointTarget = getenv('SHIELD_BROWSER_FIXTURE_ENDPOINT_TARGET');
$tokenFile = getenv('SHIELD_BROWSER_FIXTURE_TOKEN_FILE');
$fixtureToken = getenv('SHIELD_BROWSER_FIXTURE_TOKEN');
if ( !is_string($endpointTarget) || $endpointTarget === '' || !is_string($tokenFile) || $tokenFile === '' || !is_string($fixtureToken) || $fixtureToken === '' ) {
	fwrite(STDERR, "missing browser fixture endpoint environment\n");
	exit(2);
}
if ( !is_file($endpointSource) ) {
	fwrite(STDERR, "fixture endpoint source missing\n");
	exit(3);
}
if ( !is_dir(dirname($endpointTarget)) && !mkdir(dirname($endpointTarget), 0777, true) && !is_dir(dirname($endpointTarget)) ) {
	fwrite(STDERR, "failed to create mu-plugins directory\n");
	exit(4);
}
if ( !copy($endpointSource, $endpointTarget) ) {
	fwrite(STDERR, "failed to install fixture endpoint\n");
	exit(5);
}
if ( file_put_contents($tokenFile, $fixtureToken) === false ) {
	fwrite(STDERR, "failed to write fixture token\n");
	exit(6);
}
PHP;
		$this->processRunner->runOrThrow(
			[
				'docker',
				'exec',
				'-e',
				'SHIELD_BROWSER_FIXTURE_ENDPOINT_TARGET='.self::BROWSER_FIXTURE_ENDPOINT_TARGET,
				'-e',
				'SHIELD_BROWSER_FIXTURE_TOKEN_FILE='.self::BROWSER_FIXTURE_TOKEN_FILE,
				'-e',
				'SHIELD_BROWSER_FIXTURE_TOKEN='.$fixtureToken,
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir,
			$onOutput
		);
	}

	private function isBrowserLaneReady( string $rootDir, string $containerId ) :bool {
		$script = 'echo is_file('.\var_export( self::BROWSER_LANE_READY_MARKER, true ).') ? file_get_contents('.\var_export( self::BROWSER_LANE_READY_MARKER, true ).') : "";';
		$process = $this->processRunner->run(
			[
				'docker',
				'exec',
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir,
			static function () :void {}
		);
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			return false;
		}

		$decoded = \json_decode( \trim( $process->getOutput() ), true );
		if ( !\is_array( $decoded ) ) {
			return false;
		}

		return (int)( $decoded[ 'schema_version' ] ?? 0 ) === self::BROWSER_LANE_READY_SCHEMA_VERSION
			&& (string)( $decoded[ 'site_url' ] ?? '' ) === $this->definition->siteUrl()
			&& (string)( $decoded[ 'db_name' ] ?? '' ) === $this->definition->dbName()
			&& (string)( $decoded[ 'admin_user' ] ?? '' ) === $this->definition->adminUser()
			&& (string)( $decoded[ 'profile' ] ?? '' ) === $this->definition->key();
	}

	private function writeBrowserLaneReadyMarker( string $rootDir, string $containerId ) :void {
		$marker = \json_encode( [
			'schema_version' => self::BROWSER_LANE_READY_SCHEMA_VERSION,
			'site_url'       => $this->definition->siteUrl(),
			'db_name'        => $this->definition->dbName(),
			'admin_user'     => $this->definition->adminUser(),
			'profile'        => $this->definition->key(),
		], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );

		$script = <<<'PHP'
$markerPath = getenv('SHIELD_BROWSER_READY_MARKER');
$markerJson = getenv('SHIELD_BROWSER_READY_JSON');
if ( !is_string($markerPath) || $markerPath === '' || !is_string($markerJson) || $markerJson === '' ) {
	fwrite(STDERR, "missing browser readiness environment\n");
	exit(2);
}
if ( file_put_contents($markerPath, $markerJson) === false ) {
	fwrite(STDERR, "failed to write browser readiness marker\n");
	exit(3);
}
PHP;
		$this->processRunner->runOrThrow(
			[
				'docker',
				'exec',
				'-e',
				'SHIELD_BROWSER_READY_MARKER='.self::BROWSER_LANE_READY_MARKER,
				'-e',
				'SHIELD_BROWSER_READY_JSON='.$marker,
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir
		);
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function buildComposeCommandForExecution( array $composeFiles, array $subCommand ) :array {
		$command = [ 'docker', 'compose' ];
		foreach ( $composeFiles as $composeFile ) {
			$command[] = '-f';
			$command[] = $composeFile;
		}
		return \array_merge( $command, $subCommand );
	}

	/**
	 * @param string[] $command
	 */
	private function diagnoseCommandFailure(
		string $summary,
		array $command,
		int $exitCode,
		string $stdout = '',
		string $stderr = ''
	) :string {
		$message = $summary
			."\nLane key: ".$this->definition->key()
			."\nSite URL: ".$this->definition->siteUrl()
			."\nDatabase: ".$this->definition->dbName()
			."\nCompose project: ".$this->definition->composeProjectName()
			."\nExit code: ".$exitCode
			."\nCommand: ".$this->formatCommand( $command );
		if ( \trim( $stderr ) !== '' ) {
			$message .= "\nStderr: ".$this->trimDiagnosticBuffer( $stderr );
		}
		if ( \trim( $stdout ) !== '' ) {
			$message .= "\nStdout: ".$this->trimDiagnosticBuffer( $stdout );
		}
		$message .= "\nNext diagnostic: SHIELD_BROWSER_LANE_INDEX=".$this->extractLaneIndexForDiagnostic()
			.' php bin/shield test:site:status';

		return $message;
	}

	/**
	 * @param string[] $command
	 */
	private function formatCommand( array $command ) :string {
		return \implode( ' ', \array_map(
			static fn( string $part ) :string => \preg_match( '/\s/', $part ) === 1 ? '"'.$part.'"' : $part,
			$command
		) );
	}

	private function trimDiagnosticBuffer( string $buffer ) :string {
		$buffer = \trim( $buffer );
		if ( \strlen( $buffer ) <= 1200 ) {
			return $buffer;
		}
		return \substr( $buffer, 0, 1200 ).'...';
	}

	private function writeProgress( string $message, ?callable $onOutput = null ) :void {
		if ( $onOutput !== null ) {
			$onOutput( Process::OUT, $message.\PHP_EOL );
			return;
		}

		echo $message.\PHP_EOL;
	}

	private function extractLaneIndexForDiagnostic() :string {
		if ( \preg_match( '/browser-lane-(\d+)/', $this->definition->key(), $matches ) === 1 ) {
			return $matches[ 1 ];
		}
		return '1';
	}

}

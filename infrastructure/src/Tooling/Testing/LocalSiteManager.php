<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class LocalSiteManager {

	private const COMPOSE_FILE = 'tests/docker/docker-compose.local-site.yml';
	private const DB_SERVICE_NAME = 'db';
	private const WORDPRESS_SERVICE_NAME = 'wordpress';
	private const WPCLI_SERVICE_NAME = 'wp-cli';

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

	public function reset( string $rootDir, bool $requirePlaywright = false ) :int {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );

		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$this->buildComposeFiles(),
			[ 'down', '-v', '--remove-orphans' ],
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
		if ( $exitCode !== 0 ) {
			return $exitCode;
		}
		$this->ensureReadyAfterPreflight( $rootDir );
		return 0;
	}

	/**
	 * @return array{site_url:string,site_healthy:bool,port_open:bool,admin_user:string}
	 */
	public function status( string $rootDir ) :array {
		$this->environmentResolver->assertDockerReady( $rootDir );

		return [
			'site_url' => $this->definition->siteUrl(),
			'site_healthy' => $this->isSiteHealthy(),
			'port_open' => $this->probe->isTcpPortOpen( $this->definition->siteHost(), $this->definition->sitePort() ),
			'admin_user' => $this->definition->adminUser(),
		];
	}

	public function ensureReady( string $rootDir, bool $requirePlaywright ) :void {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );
		$this->ensureReadyAfterPreflight( $rootDir );
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function ensureReadyAfterPreflight( string $rootDir, ?callable $onOutput = null ) :void {
		$envOverrides = $this->buildRuntimeEnvOverrides( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$containerId = $this->resolveOrStartWordpressContainer( $rootDir, $composeFiles, $envOverrides, $onOutput );

		$this->refreshRuntimeAndAssertHealthy( $rootDir, $containerId, $onOutput );
		$this->provisionBaselineAndAssertHealthy( $rootDir, $envOverrides, $onOutput );
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
		$envOverrides['SHIELD_LOCAL_SITE_PORT'] = (string)$this->definition->sitePort();
		$envOverrides['SHIELD_LOCAL_SITE_PROFILE'] = $this->definition->key();
		return $envOverrides;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			self::COMPOSE_FILE,
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
			self::COMPOSE_FILE,
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
			self::COMPOSE_FILE,
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
					$this->definition->label().' is already running but unhealthy before runtime refresh.'
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
			[
				'up',
				'-d',
				self::DB_SERVICE_NAME,
				self::WORDPRESS_SERVICE_NAME,
			],
			$envOverrides,
			$onOutput
		);
		if ( $exitCode !== 0 ) {
			throw new \RuntimeException( 'Failed to start the '.$this->definition->label().' Docker services.' );
		}
	}

	private function waitForWordpressStartup() :void {
		if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 90 ) ) {
			throw new \RuntimeException( 'Local WordPress site did not become ready in time.' );
		}
	}

	private function refreshRuntimeAndAssertHealthy(
		string $rootDir,
		string $containerId,
		?callable $onOutput = null
	) :void {
		$this->runtimeRefresher->refresh( $rootDir, $containerId, $onOutput );
		if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 30 ) ) {
			throw new \RuntimeException( $this->definition->label().' is unhealthy after runtime refresh.' );
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
			throw new \RuntimeException( $this->definition->label().' is unhealthy after provisioning.' );
		}
	}
}

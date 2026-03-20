<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

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

	private LocalSiteDefinition $definition;

	public function __construct(
		LocalSiteDefinition $definition,
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteProbe $probe = null,
		?LocalSiteRuntimeRefresher $runtimeRefresher = null
	) {
		$this->definition = $definition;
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->probe = $probe ?? new LocalSiteProbe();
		$this->runtimeRefresher = $runtimeRefresher ?? new LocalSiteRuntimeRefresher( $this->processRunner );
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
			$this->buildEnvOverrides( $rootDir )
		);
	}

	/**
	 * @param string[] $wpCliArgs
	 */
	public function wp( string $rootDir, array $wpCliArgs ) :int {
		$this->ensureReady( $rootDir, false );

		return $this->processRunner->runForExitCode(
			$this->buildWpCliCommand( $wpCliArgs ),
			$rootDir,
			null,
			$this->buildEnvOverrides( $rootDir )
		);
	}

	public function reset( string $rootDir ) :int {
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$this->buildComposeFiles(),
			[ 'down', '-v', '--remove-orphans' ],
			$this->buildEnvOverrides( $rootDir )
		);
		if ( $exitCode !== 0 ) {
			return $exitCode;
		}
		$this->ensureReady( $rootDir, false );
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
		$envOverrides = $this->buildEnvOverrides( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$containerId = $this->runtimeRefresher->resolveServiceContainerId(
			$rootDir,
			$composeFiles,
			self::WORDPRESS_SERVICE_NAME,
			$envOverrides
		);

		if ( $containerId === '' ) {
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

			$exitCode = $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				[
					'up',
					'-d',
					self::DB_SERVICE_NAME,
					self::WORDPRESS_SERVICE_NAME,
				],
				$envOverrides
			);
			if ( $exitCode !== 0 ) {
				throw new \RuntimeException( 'Failed to start the '.$this->definition->label().' Docker services.' );
			}

			if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 90 ) ) {
				throw new \RuntimeException( 'Local WordPress site did not become ready in time.' );
			}

			$containerId = $this->runtimeRefresher->resolveServiceContainerId(
				$rootDir,
				$composeFiles,
				self::WORDPRESS_SERVICE_NAME,
				$envOverrides
			);
			if ( $containerId === '' ) {
				throw new \RuntimeException( $this->definition->label().' WordPress container did not resolve after startup.' );
			}
		}
		elseif ( !$this->isSiteHealthy() ) {
			throw new \RuntimeException( $this->definition->label().' is already running but unhealthy before browser runtime refresh.' );
		}

		$this->runtimeRefresher->refresh( $rootDir, $containerId );
		if ( !$this->probe->waitForHttpReady( $this->definition->siteUrl().'/wp-login.php', 30 ) ) {
			throw new \RuntimeException( $this->definition->label().' is unhealthy after browser runtime refresh.' );
		}

		if ( $this->processRunner->runForExitCode(
			$this->buildProvisionCommand(),
			$rootDir,
			null,
			$envOverrides
		) !== 0 ) {
			throw new \RuntimeException( 'Failed to provision the '.$this->definition->label().' baseline.' );
		}

		if ( !$this->isSiteHealthy() ) {
			throw new \RuntimeException( $this->definition->label().' is unhealthy after provisioning.' );
		}
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildEnvOverrides( string $rootDir ) :array {
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
			'-e',
			'SHIELD_LOCAL_SITE_URL='.$this->definition->siteUrl(),
			'-e',
			'SHIELD_LOCAL_SITE_TITLE='.$this->definition->siteTitle(),
			'-e',
			'SHIELD_LOCAL_SITE_PROFILE='.$this->definition->key(),
			'-e',
			'SHIELD_LOCAL_SITE_ADMIN_USER='.$this->definition->adminUser(),
			'-e',
			'SHIELD_LOCAL_SITE_ADMIN_PASSWORD='.$this->definition->adminPassword(),
			'-e',
			'SHIELD_LOCAL_SITE_ADMIN_EMAIL='.$this->definition->adminEmail(),
		];
		$command = \array_merge( $command, [
			self::WPCLI_SERVICE_NAME,
			'sh',
			'/app/tests/docker/provision-local-site.sh',
		] );

		return $command;
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
			Path::join( $rootDir, 'plugin.json' )
				=> "plugin.json is missing. Run 'composer build:config'.",
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
	}

	private function isSiteHealthy() :bool {
		return $this->probe->isHttpReady( $this->definition->siteUrl().'/wp-admin/' );
	}
}

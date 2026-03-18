<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class LocalDevSiteManager {

	public const SITE_URL = 'http://127.0.0.1:8888';
	public const SITE_HOST = '127.0.0.1';
	public const SITE_PORT = 8888;
	public const ADMIN_USER = 'admin';
	public const ADMIN_PASSWORD = 'password';

	private const COMPOSE_FILE = 'tests/docker/docker-compose.local-site.yml';
	private const COMPOSE_PROJECT_NAME = 'shield-local-site';
	private const DB_SERVICE_NAME = 'db';
	private const WORDPRESS_SERVICE_NAME = 'wordpress';
	private const WPCLI_SERVICE_NAME = 'wp-cli';

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalDevSiteProbe $probe;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalDevSiteProbe $probe = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->probe = $probe ?? new LocalDevSiteProbe();
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
			'site_url' => self::SITE_URL,
			'site_healthy' => $this->isSiteHealthy(),
			'port_open' => $this->probe->isTcpPortOpen( self::SITE_HOST, self::SITE_PORT ),
			'admin_user' => self::ADMIN_USER,
		];
	}

	public function ensureReady( string $rootDir, bool $requirePlaywright, bool $isBrowserTest = false ) :void {
		$this->runPreflightChecks( $rootDir, $requirePlaywright );

		if ( !$this->isSiteHealthy() ) {
			if ( $this->probe->isTcpPortOpen( self::SITE_HOST, self::SITE_PORT ) ) {
				throw new \RuntimeException(
					sprintf(
						'Port %d is already in use, but the Shield local site is not responding at %s.',
						self::SITE_PORT,
						self::SITE_URL
					)
				);
			}

			$exitCode = $this->dockerComposeExecutor->run(
				$rootDir,
				$this->buildComposeFiles(),
				[
					'up',
					'-d',
					self::DB_SERVICE_NAME,
					self::WORDPRESS_SERVICE_NAME,
				],
				$this->buildEnvOverrides( $rootDir )
			);
			if ( $exitCode !== 0 ) {
				throw new \RuntimeException( 'Failed to start the Shield local site Docker services.' );
			}

			if ( !$this->probe->waitForHttpReady( self::SITE_URL.'/wp-login.php', 90 ) ) {
				throw new \RuntimeException( 'Local WordPress site did not become ready in time.' );
			}
		}

		if ( $this->processRunner->runForExitCode(
			$this->buildProvisionCommand( $isBrowserTest ),
			$rootDir,
			null,
			$this->buildEnvOverrides( $rootDir )
		) !== 0 ) {
			throw new \RuntimeException( 'Failed to provision the Shield local site baseline.' );
		}
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildEnvOverrides( string $rootDir ) :array {
		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
			self::COMPOSE_PROJECT_NAME,
			true
		);
		$envOverrides['PHP_VERSION'] = $this->environmentResolver->resolvePhpVersion( $rootDir );
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
	private function buildProvisionCommand( bool $isBrowserTest = false ) :array {
		$command = [
			'docker',
			'compose',
			'-f',
			self::COMPOSE_FILE,
			'run',
			'--rm',
			'-T',
			'-e',
			'SHIELD_LOCAL_SITE_URL='.self::SITE_URL,
			'-e',
			'SHIELD_LOCAL_SITE_ADMIN_USER='.self::ADMIN_USER,
			'-e',
			'SHIELD_LOCAL_SITE_ADMIN_PASSWORD='.self::ADMIN_PASSWORD,
		];
		if ( $isBrowserTest ) {
			$command[] = '-e';
			$command[] = 'SHIELD_BROWSER_TEST_INTRO=1';
		}
		$command = \array_merge( $command, [
			self::WPCLI_SERVICE_NAME,
			'sh',
			'/app/tests/docker/provision-local-site.sh',
		] );

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
		return $this->probe->isHttpReady( self::SITE_URL.'/wp-admin/' );
	}
}

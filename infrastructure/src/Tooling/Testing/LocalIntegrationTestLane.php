<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class LocalIntegrationTestLane {

	private const COMPOSE_FILE = 'tests/docker/docker-compose.local-db.yml';
	private const COMPOSE_PROJECT_NAME = 'shield-local-db';
	private const MYSQL_SERVICE_NAME = 'mysql-local';
	private const DB_NAME = 'wordpress_test_local';
	private const DB_USER = 'root';
	private const DB_PASS = 'testpass';
	private const DB_HOST = '127.0.0.1:3311';
	private const WP_VERSION = 'latest';
	private const SKIP_DB_CREATE = true;

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalWpTestsInstallerCommandBuilder $installerCommandBuilder;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?BashCommandResolver $bashCommandResolver = null,
		?LocalWpTestsInstallerCommandBuilder $installerCommandBuilder = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$resolvedBashCommandResolver = $bashCommandResolver ?? new BashCommandResolver();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner,
			$resolvedBashCommandResolver
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->installerCommandBuilder = $installerCommandBuilder ?? new LocalWpTestsInstallerCommandBuilder(
			$resolvedBashCommandResolver
		);
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	public function run( string $rootDir, bool $dbDown = false, array $phpunitArgs = [] ) :int {
		echo 'Mode: integration-local'.\PHP_EOL;

		$this->environmentResolver->assertDockerReady( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
			self::COMPOSE_PROJECT_NAME,
			true
		);

		if ( $dbDown ) {
			return $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				$this->buildComposeDownCommand(),
				$envOverrides
			);
		}

		if ( $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			$this->buildComposeUpCommand(),
			$envOverrides
		) !== 0 ) {
			return 1;
		}

		if ( $this->processRunner->runForExitCode( $this->buildInstallerCommand(), $rootDir, null, $envOverrides ) !== 0 ) {
			return 1;
		}

		if ( $this->processRunner->runForExitCode( $this->buildBuildConfigCommand(), $rootDir, null, $envOverrides ) !== 0 ) {
			return 1;
		}

		return $this->processRunner->runForExitCode(
			$this->buildPhpUnitCommand( $phpunitArgs ),
			$rootDir,
			null,
			$envOverrides
		);
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
	private function buildComposeDownCommand() :array {
		return [ 'down', '-v', '--remove-orphans' ];
	}

	/**
	 * @return string[]
	 */
	private function buildComposeUpCommand() :array {
		return [ 'up', '-d', '--wait', self::MYSQL_SERVICE_NAME ];
	}

	/**
	 * @return string[]
	 */
	private function buildInstallerCommand() :array {
		return $this->installerCommandBuilder->build(
			self::DB_NAME,
			self::DB_USER,
			self::DB_PASS,
			self::DB_HOST,
			self::WP_VERSION,
			self::SKIP_DB_CREATE
		);
	}

	/**
	 * @return string[]
	 */
	private function buildBuildConfigCommand() :array {
		return [
			\PHP_BINARY,
			'./bin/build-config.php',
		];
	}

	/**
	 * @param string[] $phpunitArgs
	 * @return string[]
	 */
	private function buildPhpUnitCommand( array $phpunitArgs ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-integration.xml',
			],
			$phpunitArgs
		);
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class LocalIntegrationTestLane {

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private BashCommandResolver $bashCommandResolver;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?BashCommandResolver $bashCommandResolver = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver(
			$this->processRunner,
			$bashCommandResolver
		);
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->bashCommandResolver = $bashCommandResolver ?? new BashCommandResolver();
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	public function run( string $rootDir, bool $dbDown = false, array $phpunitArgs = [] ) :int {
		echo 'Mode: integration-local'.\PHP_EOL;

		$this->environmentResolver->assertDockerReady( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$envOverrides = $this->buildDockerProcessEnvOverrides();

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

		if ( $this->runCommand( $this->buildInstallerCommand(), $rootDir, $envOverrides ) !== 0 ) {
			return 1;
		}

		if ( $this->runCommand( $this->buildBuildConfigCommand(), $rootDir, $envOverrides ) !== 0 ) {
			return 1;
		}

		return $this->runCommand(
			$this->buildPhpUnitCommand( $phpunitArgs ),
			$rootDir,
			$envOverrides
		);
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runCommand( array $command, string $rootDir, ?array $envOverrides = null ) :int {
		return $this->processRunner->run( $command, $rootDir, null, $envOverrides )->getExitCode() ?? 1;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			'tests/docker/docker-compose.local-db.yml',
		];
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildDockerProcessEnvOverrides() :array {
		return [
			'COMPOSE_PROJECT_NAME' => 'shield-local-db',
			'DOCKER_BUILDKIT' => '1',
			'MSYS_NO_PATHCONV' => '1',
			'SHIELD_PACKAGE_PATH' => false,
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
		return [ 'up', '-d', '--wait', 'mysql-local' ];
	}

	/**
	 * @return string[]
	 */
	private function buildInstallerCommand() :array {
		return $this->buildInstallerCommandForOs( \PHP_OS_FAMILY );
	}

	/**
	 * @return string[]
	 */
	private function buildInstallerCommandForOs( string $osFamily ) :array {
		if ( $osFamily === 'Windows' ) {
			return [
				'powershell',
				'-NoProfile',
				'-ExecutionPolicy',
				'Bypass',
				'-File',
				'./bin/install-wp-tests.ps1',
				'-DB_NAME',
				'wordpress_test_local',
				'-DB_USER',
				'root',
				'-DB_PASS',
				'testpass',
				'-DB_HOST',
				'127.0.0.1:3311',
				'-WP_VERSION',
				'latest',
			];
		}

		return [
			$this->bashCommandResolver->resolve(),
			'./bin/install-wp-tests.sh',
			'wordpress_test_local',
			'root',
			'testpass',
			'127.0.0.1:3311',
			'latest',
			'true',
		];
	}

	/**
	 * @return string[]
	 */
	private function buildBuildConfigCommand() :array {
		return [
			'php',
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
				'php',
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-integration.xml',
			],
			$phpunitArgs
		);
	}
}

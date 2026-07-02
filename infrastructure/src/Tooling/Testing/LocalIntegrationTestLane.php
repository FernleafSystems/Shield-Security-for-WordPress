<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class LocalIntegrationTestLane {

	private const COMPOSE_FILE = 'tests/docker/docker-compose.local-db.yml';
	private const COMPOSE_PROJECT_NAME = 'shield-local-db';
	private const MYSQL_SERVICE_NAME = 'mysql-local';
	private const DB_NAME = 'wordpress_test_local';
	private const DB_USER = 'root';
	private const DB_PASS = 'testpass';
	private const DB_HOST_NAME = '127.0.0.1';
	private const DB_PORT = 3311;
	private const DB_HOST = self::DB_HOST_NAME.':'.self::DB_PORT;
	private const WP_VERSION = 'latest';
	private const SKIP_DB_CREATE = true;
	private const LOCK_DIR_NAME = 'shield-test-locks';
	private const LOCK_FILE = 'integration-local.lock';
	private const DEFAULT_WAIT_SECONDS = 600;
	private const DATABASE_READY_WAIT_SECONDS = 60;
	private const REUSABLE_DOCKER_RUN_ID = 'integration-local-reusable';
	private const REUSABLE_DOCKER_EXPIRES_AT = '2037-12-31T23:59:59+00:00';

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalWpTestsInstallerCommandBuilder $installerCommandBuilder;

	private LocalWpTestsConfigGuard $wpTestsConfigGuard;

	private ?string $lockDir;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?BashCommandResolver $bashCommandResolver = null,
		?LocalWpTestsInstallerCommandBuilder $installerCommandBuilder = null,
		?string $lockDir = null,
		?LocalWpTestsConfigGuard $wpTestsConfigGuard = null
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
		$this->wpTestsConfigGuard = $wpTestsConfigGuard ?? new LocalWpTestsConfigGuard();
		$this->lockDir = $lockDir;
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	public function run( string $rootDir, bool $dbDown = false, array $phpunitArgs = [], bool $showDockerOutput = false ) :int {
		echo 'Mode: integration-local'.\PHP_EOL;

		return $this->withLaneLock( $rootDir, function () use ( $rootDir, $dbDown, $phpunitArgs, $showDockerOutput ) :int {
			return $this->runWithLock( $rootDir, $dbDown, $phpunitArgs, $showDockerOutput );
		} );
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	private function runWithLock( string $rootDir, bool $dbDown, array $phpunitArgs, bool $showDockerOutput ) :int {
		$this->environmentResolver->assertDockerReady( $rootDir );
		$composeFiles = $this->buildComposeFiles();
		$envOverrides = \array_merge(
			$this->environmentResolver->buildDockerProcessEnvOverrides(
				self::COMPOSE_PROJECT_NAME,
				true
			),
			DockerCleanupPolicy::integrationLocal()->labelEnvironment(
				self::REUSABLE_DOCKER_RUN_ID,
				DockerHarnessLabels::LIFECYCLE_REUSABLE,
				'integration-local',
				self::REUSABLE_DOCKER_EXPIRES_AT,
				self::REUSABLE_DOCKER_RUN_ID,
				DockerHarnessLabels::LIFECYCLE_REUSABLE,
				self::REUSABLE_DOCKER_EXPIRES_AT
			)
		);
		$phpUnitEnvOverrides = \array_merge( $envOverrides, $this->buildWordPressTestEnvOverrides() );

		if ( $dbDown ) {
			return $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				$this->buildComposeDownCommand(),
				$envOverrides,
				null,
				$showDockerOutput
			);
		}

		if ( $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			$this->buildComposeUpCommand(),
			$envOverrides,
			null,
			$showDockerOutput
		) !== 0 ) {
			return 1;
		}

		$this->waitForHostDatabaseReady( $rootDir, $envOverrides );
		$this->wpTestsConfigGuard->removeIfStale( $this->wordPressTestsDir(), $this->expectedWordPressTestDbConstants() );

		if ( $this->processRunner->runForExitCode( $this->buildInstallerCommand(), $rootDir, null, $envOverrides ) !== 0 ) {
			return 1;
		}
		$this->wpTestsConfigGuard->assertMatches( $this->wordPressTestsDir(), $this->expectedWordPressTestDbConstants() );

		if ( $this->processRunner->runForExitCode( $this->buildBuildConfigCommand(), $rootDir, null, $phpUnitEnvOverrides ) !== 0 ) {
			return 1;
		}

		return $this->processRunner->runForExitCode(
			$this->buildPhpUnitCommand( $phpunitArgs ),
			$rootDir,
			null,
			$phpUnitEnvOverrides
		);
	}

	/**
	 * @param callable():int $callback
	 */
	private function withLaneLock( string $rootDir, callable $callback ) :int {
		$waitSeconds = $this->waitSeconds();
		$lockDir = $this->resolveLockDir();
		if ( !\is_dir( $lockDir ) && !@\mkdir( $lockDir, 0777, true ) && !\is_dir( $lockDir ) ) {
			throw new \RuntimeException( 'Failed to create integration lane lock directory: '.$lockDir );
		}

		$lockPath = Path::join( $lockDir, self::LOCK_FILE );
		$handle = \fopen( $lockPath, 'c+' );
		if ( $handle === false ) {
			throw new \RuntimeException( 'Failed to open integration lane lock file: '.$lockPath );
		}

		$startedAt = \time();
		$reportedWaiting = false;
		try {
			do {
				if ( \flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
					$this->writeLeaseMetadata( $handle, $rootDir );
					echo 'Integration lane: acquired lock'.\PHP_EOL;
					return $callback();
				}
				if ( !$reportedWaiting ) {
					echo 'Integration lane: waiting for lock'.\PHP_EOL;
					$reportedWaiting = true;
				}
				\usleep( 500000 );
			} while ( \time() - $startedAt < $waitSeconds );

			throw new \RuntimeException(
				'No integration-local test lane became available within '.$waitSeconds.' seconds. '
				.'Lock: '.$lockPath.'. '
				.'Metadata: '.$this->readLockMetadata( $handle )
			);
		}
		finally {
			if ( \is_resource( $handle ) ) {
				@\flock( $handle, \LOCK_UN );
				@\fclose( $handle );
			}
		}
	}

	private function resolveLockDir() :string {
		if ( $this->lockDir !== null ) {
			return $this->lockDir;
		}

		return Path::join( \rtrim( \sys_get_temp_dir(), "\\/" ), self::LOCK_DIR_NAME );
	}

	private function waitSeconds() :int {
		$value = \getenv( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS' );
		if ( $value === false || $value === '' ) {
			return self::DEFAULT_WAIT_SECONDS;
		}
		if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
			throw new \InvalidArgumentException( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS must be a positive integer.' );
		}

		return (int)$value;
	}

	/**
	 * @param resource $handle
	 */
	private function writeLeaseMetadata( $handle, string $rootDir ) :void {
		\rewind( $handle );
		\ftruncate( $handle, 0 );
		\fwrite( $handle, \json_encode( [
			'resource' => 'integration-local',
			'compose_project' => self::COMPOSE_PROJECT_NAME,
			'db_name' => self::DB_NAME,
			'db_host' => self::DB_HOST,
			'pid' => \getmypid(),
			'cwd' => (string)\getcwd(),
			'root_dir' => $rootDir,
			'acquired_at_unix' => \time(),
		], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ).\PHP_EOL );
		\fflush( $handle );
	}

	/**
	 * @param resource $handle
	 */
	private function readLockMetadata( $handle ) :string {
		\rewind( $handle );
		$metadata = @\stream_get_contents( $handle );
		if ( $metadata === false || \trim( $metadata ) === '' ) {
			return 'unavailable';
		}

		return \trim( $metadata );
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

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function waitForHostDatabaseReady( string $rootDir, array $envOverrides ) :void {
		$command = $this->buildHostDatabaseReadyCommand();
		$startedAt = \time();
		$lastOutput = '';
		echo 'Integration lane: waiting for host MySQL TCP readiness'.\PHP_EOL;
		do {
			$process = $this->processRunner->run(
				$command,
				$rootDir,
				static function ( string $type, string $buffer ) :void {
				},
				$envOverrides
			);
			if ( ( $process->getExitCode() ?? 1 ) === 0 ) {
				return;
			}
			$lastOutput = \trim( $process->getErrorOutput().$process->getOutput() );
			\usleep( 500000 );
		} while ( \time() - $startedAt < self::DATABASE_READY_WAIT_SECONDS );

		throw new \RuntimeException(
			'Integration-local MySQL did not become reachable from host PHP within '
			.self::DATABASE_READY_WAIT_SECONDS.' seconds at '.self::DB_HOST.'.'
			.( $lastOutput === '' ? '' : ' Last probe output: '.$lastOutput )
		);
	}

	/**
	 * @return string[]
	 */
	private function buildHostDatabaseReadyCommand() :array {
		return [
			\PHP_BINARY,
			'-r',
			$this->buildHostDatabaseReadyProbeScript(),
		];
	}

	private function buildHostDatabaseReadyProbeScript() :string {
		return \sprintf(
			<<<'PHP'
if ( !extension_loaded( 'mysqli' ) ) {
	fwrite( STDERR, 'PHP mysqli extension is required for integration-local MySQL readiness.' );
	exit( 2 );
}
$mysqli = mysqli_init();
if ( !$mysqli instanceof mysqli ) {
	fwrite( STDERR, 'Failed to initialize mysqli.' );
	exit( 2 );
}
$mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, 2 );
if ( !@$mysqli->real_connect( %s, %s, %s, %s, %d ) ) {
	fwrite( STDERR, mysqli_connect_error() ?: $mysqli->connect_error ?: 'Host MySQL TCP connection failed.' );
	exit( 1 );
}
$result = $mysqli->query( 'SELECT 1' );
if ( $result === false ) {
	fwrite( STDERR, $mysqli->error ?: 'Host MySQL SELECT 1 failed.' );
	exit( 1 );
}
exit( 0 );
PHP,
			\var_export( self::DB_HOST_NAME, true ),
			\var_export( self::DB_USER, true ),
			\var_export( self::DB_PASS, true ),
			\var_export( self::DB_NAME, true ),
			self::DB_PORT
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function buildWordPressTestEnvOverrides() :array {
		return [
			'WP_TESTS_DIR' => $this->wordPressTestsDir(),
			'WP_CORE_DIR'  => $this->wordPressCoreDir(),
		];
	}

	/**
	 * @return array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string}
	 */
	private function expectedWordPressTestDbConstants() :array {
		return [
			'DB_NAME' => self::DB_NAME,
			'DB_USER' => self::DB_USER,
			'DB_PASSWORD' => self::DB_PASS,
			'DB_HOST' => self::DB_HOST,
		];
	}

	private function wordPressTestsDir() :string {
		return \rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress-tests-lib';
	}

	private function wordPressCoreDir() :string {
		return \rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress';
	}

}

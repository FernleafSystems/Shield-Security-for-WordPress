<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalWpTestsConfigGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalWpTestsInstallerCommandBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalIntegrationTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	private string $lockDir;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
		$this->lockDir = $this->createTrackedTempDir( 'shield-integration-locks-' );
	}

	protected function tearDown() :void {
		\putenv( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS' );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testDefaultRunIssuesComposeUpWaitAndRunsLocalCommands() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$installerCommandBuilder = $this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] );
		$wpTestsConfigGuard = new RecordingLocalWpTestsConfigGuard();
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$installerCommandBuilder,
			$this->lockDir,
			$wpTestsConfigGuard
		);

		$exitCode = $this->runLaneSilenced( $lane, false, [ '--filter', 'RuleBuilderTest' ] );

		$this->assertSame( 0, $exitCode );
		$this->assertLaneLockMetadataWritten();
		$this->assertLaneLockReleased();
		$this->assertTrue( $environmentResolver->assertDockerReadyCalled );
		$this->assertCount( 1, $dockerComposeExecutor->calls );
		$this->assertSame(
			[
				'up',
				'-d',
				'--wait',
				'mysql-local',
			],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertFalse( $dockerComposeExecutor->calls[ 0 ][ 'show_docker_output' ] );
		$this->assertSame(
			[
				'tests/docker/docker-compose.local-db.yml',
			],
			$dockerComposeExecutor->calls[ 0 ][ 'compose_files' ]
		);
		$this->assertDockerEnvOverrides( $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ] );

		$this->assertCount( 1, $installerCommandBuilder->calls );
		$this->assertCount( 1, $wpTestsConfigGuard->removeIfStaleCalls );
		$this->assertCount( 1, $wpTestsConfigGuard->assertMatchesCalls );
		$this->assertSame(
			[
				'DB_NAME' => 'wordpress_test_local',
				'DB_USER' => 'root',
				'DB_PASSWORD' => 'testpass',
				'DB_HOST' => '127.0.0.1:3311',
			],
			$wpTestsConfigGuard->removeIfStaleCalls[ 0 ][ 'expected' ]
		);
		$this->assertSame(
			$wpTestsConfigGuard->removeIfStaleCalls[ 0 ],
			$wpTestsConfigGuard->assertMatchesCalls[ 0 ]
		);
		$this->assertSame(
			[
				'db_name' => 'wordpress_test_local',
				'db_user' => 'root',
				'db_pass' => 'testpass',
				'db_host' => '127.0.0.1:3311',
				'wp_version' => 'latest',
				'skip_db_create' => true,
			],
			$installerCommandBuilder->calls[ 0 ]
		);

		$this->assertCount( 5, $processRunner->calls );
		$this->assertHostDatabaseReadyCommand( $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertHostDatabaseResetCommand( $processRunner->calls[ 1 ][ 'command' ] );
		$this->assertSame( [ 'custom-installer' ], $processRunner->calls[ 2 ][ 'command' ] );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/build-config.php',
			],
			$processRunner->calls[ 3 ][ 'command' ]
		);
		$this->assertPhpUnitEnvOverrides( $processRunner->calls[ 3 ][ 'env_overrides' ] );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-integration.xml',
				'--filter',
				'RuleBuilderTest',
			],
			$processRunner->calls[ 4 ][ 'command' ]
		);
		$this->assertDockerEnvOverrides( $processRunner->calls[ 0 ][ 'env_overrides' ] );
		$this->assertDockerEnvOverrides( $processRunner->calls[ 1 ][ 'env_overrides' ] );
		$this->assertDockerEnvOverrides( $processRunner->calls[ 2 ][ 'env_overrides' ] );
		$this->assertPhpUnitEnvOverrides( $processRunner->calls[ 4 ][ 'env_overrides' ] );
	}

	public function testDatabaseResetFailureStopsBeforeInstallerAndPhpunit() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 7 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$this->createRecordingEnvironmentResolver(),
			$dockerComposeExecutor,
			null,
			$this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] ),
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to recreate integration-local database wordpress_test_local.' );
		try {
			$this->runLaneSilenced( $lane );
		}
		finally {
			$this->assertCount( 2, $processRunner->calls );
			$this->assertHostDatabaseReadyCommand( $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertHostDatabaseResetCommand( $processRunner->calls[ 1 ][ 'command' ] );
		}
	}

	public function testDbDownOnlyRunsComposeDownAndExits() :void {
		$processRunner = new RecordingProcessRunner();
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 7 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			null,
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$exitCode = $this->runLaneSilenced( $lane, true );

		$this->assertSame( 7, $exitCode );
		$this->assertLaneLockMetadataWritten();
		$this->assertLaneLockReleased();
		$this->assertTrue( $environmentResolver->assertDockerReadyCalled );
		$this->assertCount( 1, $dockerComposeExecutor->calls );
		$this->assertSame(
			[
				'down',
				'-v',
				'--remove-orphans',
			],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertFalse( $dockerComposeExecutor->calls[ 0 ][ 'show_docker_output' ] );
		$this->assertCount( 0, $processRunner->calls );
	}

	public function testDbUpAndSuiteRunCanEnableNoisyDockerOutput() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$installerCommandBuilder = $this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] );
		$wpTestsConfigGuard = new RecordingLocalWpTestsConfigGuard();

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$installerCommandBuilder,
			$this->lockDir,
			$wpTestsConfigGuard
		);

		$exitCode = $this->runLaneSilenced(
			$lane,
			false,
			[ '--filter', 'RuleBuilderTest' ],
			true
		);

		$this->assertSame( 0, $exitCode );
		$this->assertTrue( $dockerComposeExecutor->calls[ 0 ][ 'show_docker_output' ] );
	}

	public function testHeldLaneLockTimesOutBeforeTouchingDocker() :void {
		\putenv( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS=1' );
		$heldLock = $this->holdLaneLock();
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] ),
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$caught = null;
		try {
			$this->runLaneSilenced( $lane );
		}
		catch ( \RuntimeException $e ) {
			$caught = $e;
		}
		finally {
			@\flock( $heldLock, \LOCK_UN );
			@\fclose( $heldLock );
		}

		$this->assertInstanceOf( \RuntimeException::class, $caught );
		$this->assertStringContainsString( 'No integration-local test lane became available within 1 seconds', $caught->getMessage() );
		$this->assertStringContainsString( $this->laneLockPath(), $caught->getMessage() );
		$this->assertStringContainsString( 'Metadata:', $caught->getMessage() );
		$this->assertFalse( $environmentResolver->assertDockerReadyCalled );
		$this->assertSame( [], $dockerComposeExecutor->calls );
		$this->assertSame( [], $processRunner->calls );
	}

	public function testDbDownAlsoRequiresLaneLock() :void {
		\putenv( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS=1' );
		$heldLock = $this->holdLaneLock();
		$processRunner = new RecordingProcessRunner();
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			null,
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$caught = null;
		try {
			$this->runLaneSilenced( $lane, true );
		}
		catch ( \RuntimeException $e ) {
			$caught = $e;
		}
		finally {
			@\flock( $heldLock, \LOCK_UN );
			@\fclose( $heldLock );
		}

		$this->assertInstanceOf( \RuntimeException::class, $caught );
		$this->assertStringContainsString( 'No integration-local test lane became available within 1 seconds', $caught->getMessage() );
		$this->assertStringContainsString( $this->laneLockPath(), $caught->getMessage() );
		$this->assertFalse( $environmentResolver->assertDockerReadyCalled );
		$this->assertSame( [], $dockerComposeExecutor->calls );
	}

	public function testInvalidWaitSecondsEnvironmentFailsClearly() :void {
		\putenv( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS=soon' );
		$processRunner = new RecordingProcessRunner();
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			null,
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$caught = null;
		try {
			$this->runLaneSilenced( $lane );
		}
		catch ( \InvalidArgumentException $e ) {
			$caught = $e;
		}

		$this->assertInstanceOf( \InvalidArgumentException::class, $caught );
		$this->assertSame( 'SHIELD_INTEGRATION_LANE_WAIT_SECONDS must be a positive integer.', $caught->getMessage() );
		$this->assertFalse( $environmentResolver->assertDockerReadyCalled );
		$this->assertSame( [], $dockerComposeExecutor->calls );
		$this->assertSame( [], $processRunner->calls );
		$this->assertFileDoesNotExist( $this->laneLockPath() );
	}

	/** @dataProvider providerDatabaseProfiles */
	public function testDatabaseProfileSuppliesFixedComposeEnvironment(
		string $profile,
		string $expectedImage,
		string $expectedCommand
	) :void {
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			new RecordingProcessRunner(),
			$this->createRecordingEnvironmentResolver(),
			$dockerComposeExecutor,
			null,
			null,
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$this->assertSame( 0, $this->runLaneSilenced( $lane, true, [], false, $profile ) );
		$this->assertSame( $expectedImage, $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_INTEGRATION_DB_IMAGE' ] ?? null );
		$this->assertSame( $expectedCommand, $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_INTEGRATION_DB_COMMAND' ] ?? null );
	}

	public function testUnknownDatabaseProfileFailsBeforeDocker() :void {
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$lane = new LocalIntegrationTestLane(
			new RecordingProcessRunner(),
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			null,
			$this->lockDir,
			new RecordingLocalWpTestsConfigGuard()
		);

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown integration database profile: mysql-latest' );
		try {
			$this->runLaneSilenced( $lane, false, [], false, 'mysql-latest' );
		}
		finally {
			$this->assertFalse( $environmentResolver->assertDockerReadyCalled );
			$this->assertSame( [], $dockerComposeExecutor->calls );
		}
	}

	public static function providerDatabaseProfiles() :array {
		return [
			'mysql80' => [
				'mysql80',
				'mysql:8.0',
				'--default-authentication-plugin=mysql_native_password --bind-address=0.0.0.0',
			],
			'mysql56' => [ 'mysql56', 'mysql:5.6', '--bind-address=0.0.0.0' ],
			'mariadb106' => [ 'mariadb106', 'mariadb:10.6', '--bind-address=0.0.0.0' ],
		];
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	private function runLaneSilenced(
		LocalIntegrationTestLane $lane,
		bool $dbDown = false,
		array $phpunitArgs = [],
		bool $showDockerOutput = false,
		string $dbProfile = 'mysql80'
	) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $dbDown, $phpunitArgs, $showDockerOutput, $dbProfile );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function createRecordingEnvironmentResolver() :RecordingTestingEnvironmentResolver {
		return new RecordingTestingEnvironmentResolver();
	}

	/**
	 * @param string[] $command
	 */
	private function createRecordingInstallerCommandBuilder( array $command ) :RecordingLocalWpTestsInstallerCommandBuilder {
		return new RecordingLocalWpTestsInstallerCommandBuilder( $command );
	}

	/**
	 * @param string[] $command
	 */
	private function assertHostDatabaseReadyCommand( array $command ) :void {
		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertSame( '-r', $command[ 1 ] ?? null );
		$script = (string)( $command[ 2 ] ?? '' );
		$this->assertStringContainsString( 'extension_loaded( \'mysqli\' )', $script );
		$this->assertStringContainsString( 'real_connect( \'127.0.0.1\', \'root\', \'testpass\', null, 3311 )', $script );
		$this->assertStringContainsString( 'SELECT 1', $script );
	}

	/**
	 * @param string[] $command
	 */
	private function assertHostDatabaseResetCommand( array $command ) :void {
		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertSame( '-r', $command[ 1 ] ?? null );
		$script = (string)( $command[ 2 ] ?? '' );
		$this->assertStringContainsString( "real_connect( '127.0.0.1', 'root', 'testpass', null, 3311 )", $script );
		$this->assertStringContainsString( 'DROP DATABASE IF EXISTS `wordpress_test_local`', $script );
		$this->assertStringContainsString(
			'CREATE DATABASE `wordpress_test_local` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
			$script
		);
	}

	private function laneLockPath() :string {
		return Path::join( $this->lockDir, 'integration-local.lock' );
	}

	private function assertLaneLockMetadataWritten() :void {
		$this->assertFileExists( $this->laneLockPath() );
		$metadata = \json_decode( (string)\file_get_contents( $this->laneLockPath() ), true );
		$this->assertIsArray( $metadata );
		$this->assertSame( 'integration-local', $metadata[ 'resource' ] ?? null );
		$this->assertSame( 'shield-local-db', $metadata[ 'compose_project' ] ?? null );
		$this->assertSame( 'wordpress_test_local', $metadata[ 'db_name' ] ?? null );
		$this->assertSame( '127.0.0.1:3311', $metadata[ 'db_host' ] ?? null );
		$this->assertSame( 'mysql80', $metadata[ 'db_profile' ] ?? null );
		$this->assertSame( $this->projectRoot, $metadata[ 'root_dir' ] ?? null );
	}

	private function assertLaneLockReleased() :void {
		$handle = \fopen( $this->laneLockPath(), 'c+' );
		$this->assertIsResource( $handle );

		try {
			$this->assertTrue( \flock( $handle, \LOCK_EX | \LOCK_NB ) );
		}
		finally {
			@\flock( $handle, \LOCK_UN );
			@\fclose( $handle );
		}
	}

	/**
	 * @return resource
	 */
	private function holdLaneLock() {
		if ( !\is_dir( $this->lockDir ) && !\mkdir( $this->lockDir, 0777, true ) && !\is_dir( $this->lockDir ) ) {
			throw new \RuntimeException( 'Failed to create lock dir: '.$this->lockDir );
		}
		$handle = \fopen( $this->laneLockPath(), 'c+' );
		if ( $handle === false || !\flock( $handle, \LOCK_EX | \LOCK_NB ) ) {
			throw new \RuntimeException( 'Failed to hold lane lock for test.' );
		}
		\fwrite( $handle, '{"resource":"integration-local","pid":123}'.\PHP_EOL );
		\fflush( $handle );

		return $handle;
	}

	/**
	 * @return array<string,string|false>
	 */
	private function expectedDockerEnvOverrides() :array {
		return [
			'DOCKER_BUILDKIT' => '1',
			'MSYS_NO_PATHCONV' => '1',
			'COMPOSE_PROJECT_NAME' => 'shield-local-db',
			'SHIELD_PACKAGE_PATH' => false,
		];
	}

	/**
	 * @param array<string,string|false> $env
	 */
	private function assertDockerEnvOverrides( array $env ) :void {
		foreach ( $this->expectedDockerEnvOverrides() as $name => $value ) {
			$this->assertSame( $value, $env[ $name ] ?? null );
		}

		$this->assertSame( 'shield-plugin-integration-local', $env[ 'SHIELD_DOCKER_LABEL_HARNESS' ] ?? null );
		$this->assertSame( 'integration-local', $env[ 'SHIELD_DOCKER_LABEL_LANE' ] ?? null );
		$this->assertSame( 'reusable', $env[ 'SHIELD_DOCKER_CONTAINER_LIFECYCLE' ] ?? null );
		$this->assertSame( 'reusable', $env[ 'SHIELD_DOCKER_VOLUME_LIFECYCLE' ] ?? null );
		$this->assertSame( 'integration-local-reusable', $env[ 'SHIELD_DOCKER_CONTAINER_RUN_ID' ] ?? null );
		$this->assertSame(
			$env[ 'SHIELD_DOCKER_CONTAINER_RUN_ID' ] ?? null,
			$env[ 'SHIELD_DOCKER_VOLUME_RUN_ID' ] ?? null
		);
		$this->assertSame( '2037-12-31T23:59:59+00:00', $env[ 'SHIELD_DOCKER_CONTAINER_EXPIRES_AT' ] ?? null );
		$this->assertSame( 'mysql:8.0', $env[ 'SHIELD_INTEGRATION_DB_IMAGE' ] ?? null );
		$this->assertSame(
			'--default-authentication-plugin=mysql_native_password --bind-address=0.0.0.0',
			$env[ 'SHIELD_INTEGRATION_DB_COMMAND' ] ?? null
		);
		$this->assertSame(
			$env[ 'SHIELD_DOCKER_CONTAINER_EXPIRES_AT' ] ?? null,
			$env[ 'SHIELD_DOCKER_VOLUME_EXPIRES_AT' ] ?? null
		);
	}

	/**
	 * @param array<string,string|false> $env
	 */
	private function assertPhpUnitEnvOverrides( array $env ) :void {
		$this->assertDockerEnvOverrides( $env );
		$this->assertSame(
			\rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress-tests-lib',
			$env[ 'WP_TESTS_DIR' ] ?? null
		);
		$this->assertSame(
			\rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress',
			$env[ 'WP_CORE_DIR' ] ?? null
		);
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
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
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$installerCommandBuilder = $this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$installerCommandBuilder,
			$this->lockDir
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
		$this->assertSame(
			$this->expectedDockerEnvOverrides(),
			$dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ]
		);

		$this->assertCount( 1, $installerCommandBuilder->calls );
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

		$this->assertCount( 3, $processRunner->calls );
		$this->assertSame( [ 'custom-installer' ], $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/build-config.php',
			],
			$processRunner->calls[ 1 ][ 'command' ]
		);
		$this->assertSame(
			$this->expectedPhpUnitEnvOverrides(),
			$processRunner->calls[ 1 ][ 'env_overrides' ]
		);
		$this->assertSame(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-integration.xml',
				'--filter',
				'RuleBuilderTest',
			],
			$processRunner->calls[ 2 ][ 'command' ]
		);
		$this->assertSame(
			$this->expectedDockerEnvOverrides(),
			$processRunner->calls[ 0 ][ 'env_overrides' ]
		);
		$this->assertSame(
			$this->expectedPhpUnitEnvOverrides(),
			$processRunner->calls[ 2 ][ 'env_overrides' ]
		);
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
			$this->lockDir
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
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$installerCommandBuilder = $this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$installerCommandBuilder,
			$this->lockDir
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
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			null,
			$this->createRecordingInstallerCommandBuilder( [ 'custom-installer' ] ),
			$this->lockDir
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
			$this->lockDir
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
			$this->lockDir
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

	/**
	 * @param string[] $phpunitArgs
	 */
	private function runLaneSilenced(
		LocalIntegrationTestLane $lane,
		bool $dbDown = false,
		array $phpunitArgs = [],
		bool $showDockerOutput = false
	) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $dbDown, $phpunitArgs, $showDockerOutput );
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
	 * @return array<string,string|false>
	 */
	private function expectedPhpUnitEnvOverrides() :array {
		return \array_merge( $this->expectedDockerEnvOverrides(), [
			'WP_TESTS_DIR' => \rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress-tests-lib',
			'WP_CORE_DIR' => \rtrim( \sys_get_temp_dir(), "\\/" ).\DIRECTORY_SEPARATOR.'wordpress',
		] );
	}
}

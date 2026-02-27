<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LocalIntegrationTestLaneTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testDefaultRunIssuesComposeUpWaitAndRunsLocalCommands() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0, 0, 0 ] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = $this->createRecordingDockerComposeExecutor( [ 0 ] );
		$bashCommandResolver = $this->createFixedBashCommandResolver( '/custom/bash' );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$bashCommandResolver
		);

		$exitCode = $this->runLaneSilenced( $lane, false, [ '--filter', 'RuleBuilderTest' ] );

		$this->assertSame( 0, $exitCode );
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
		$this->assertSame(
			[
				'tests/docker/docker-compose.local-db.yml',
			],
			$dockerComposeExecutor->calls[ 0 ][ 'compose_files' ]
		);
		$this->assertSame(
			[
				'COMPOSE_PROJECT_NAME' => 'shield-local-db',
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'SHIELD_PACKAGE_PATH' => false,
			],
			$dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ]
		);

		$this->assertCount( 3, $processRunner->calls );

		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$this->assertSame( 'powershell', $processRunner->calls[ 0 ][ 'command' ][ 0 ] );
			$this->assertContains( '-DB_NAME', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( 'wordpress_test_local', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( '-DB_HOST', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( '127.0.0.1:3311', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( '-WP_VERSION', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( 'latest', $processRunner->calls[ 0 ][ 'command' ] );
		}
		else {
			$this->assertSame(
				[
					'/custom/bash',
					'./bin/install-wp-tests.sh',
					'wordpress_test_local',
					'root',
					'testpass',
					'127.0.0.1:3311',
					'latest',
					'true',
				],
				$processRunner->calls[ 0 ][ 'command' ]
			);
		}

		$this->assertSame(
			[
				'php',
				'./bin/build-config.php',
			],
			$processRunner->calls[ 1 ][ 'command' ]
		);
		$this->assertSame(
			[
				'php',
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-integration.xml',
				'--filter',
				'RuleBuilderTest',
			],
			$processRunner->calls[ 2 ][ 'command' ]
		);
		foreach ( $processRunner->calls as $call ) {
			$this->assertSame(
				[
					'COMPOSE_PROJECT_NAME' => 'shield-local-db',
					'DOCKER_BUILDKIT' => '1',
					'MSYS_NO_PATHCONV' => '1',
					'SHIELD_PACKAGE_PATH' => false,
				],
				$call[ 'env_overrides' ]
			);
		}
	}

	public function testInstallerCommandSelectionSupportsWindowsAndNonWindowsPaths() :void {
		$processRunner = $this->createRecordingProcessRunner( [] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = $this->createRecordingDockerComposeExecutor( [] );
		$bashCommandResolver = $this->createFixedBashCommandResolver( '/resolved/bash' );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$bashCommandResolver
		);

		$method = new \ReflectionMethod( LocalIntegrationTestLane::class, 'buildInstallerCommandForOs' );
		$method->setAccessible( true );

		$windowsCommand = $method->invoke( $lane, 'Windows' );
		$this->assertSame( 'powershell', $windowsCommand[ 0 ] );
		$this->assertContains( './bin/install-wp-tests.ps1', $windowsCommand );
		$this->assertContains( '-DB_NAME', $windowsCommand );
		$this->assertContains( 'wordpress_test_local', $windowsCommand );
		$this->assertContains( '-DB_USER', $windowsCommand );
		$this->assertContains( 'root', $windowsCommand );
		$this->assertContains( '-DB_PASS', $windowsCommand );
		$this->assertContains( 'testpass', $windowsCommand );
		$this->assertContains( '-DB_HOST', $windowsCommand );
		$this->assertContains( '127.0.0.1:3311', $windowsCommand );
		$this->assertContains( '-WP_VERSION', $windowsCommand );
		$this->assertContains( 'latest', $windowsCommand );

		$nonWindowsCommand = $method->invoke( $lane, 'Linux' );
		$this->assertSame(
			[
				'/resolved/bash',
				'./bin/install-wp-tests.sh',
				'wordpress_test_local',
				'root',
				'testpass',
				'127.0.0.1:3311',
				'latest',
				'true',
			],
			$nonWindowsCommand
		);
	}

	public function testDbDownOnlyRunsComposeDownAndExits() :void {
		$processRunner = $this->createRecordingProcessRunner( [] );
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = $this->createRecordingDockerComposeExecutor( [ 7 ] );
		$bashCommandResolver = $this->createFixedBashCommandResolver( '/custom/bash' );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$bashCommandResolver
		);

		$exitCode = $this->runLaneSilenced( $lane, true );

		$this->assertSame( 7, $exitCode );
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
		$this->assertCount( 0, $processRunner->calls );
	}

	/**
	 * @param string[] $phpunitArgs
	 */
	private function runLaneSilenced(
		LocalIntegrationTestLane $lane,
		bool $dbDown = false,
		array $phpunitArgs = []
	) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $dbDown, $phpunitArgs );
		}
		finally {
			\ob_end_clean();
		}
	}

	/**
	 * @param int[] $exitCodes
	 */
	private function createRecordingProcessRunner( array $exitCodes ) :ProcessRunner {
		return new class( $exitCodes ) extends ProcessRunner {

			/** @var array<int,array{command:array,working_dir:string,env_overrides:?array}> */
			public array $calls = [];

			/** @var int[] */
			private array $exitCodes;

			/**
			 * @param int[] $exitCodes
			 */
			public function __construct( array $exitCodes ) {
				parent::__construct();
				$this->exitCodes = $exitCodes;
			}

			public function run(
				array $command,
				string $workingDir,
				?callable $onOutput = null,
				?array $envOverrides = null
			) :Process {
				$this->calls[] = [
					'command' => $command,
					'working_dir' => $workingDir,
					'env_overrides' => $envOverrides,
				];

				$exitCode = \array_shift( $this->exitCodes );
				$process = new Process(
					[
						\PHP_BINARY,
						'-r',
						'exit('.(int)( $exitCode ?? 0 ).');',
					]
				);
				$process->run( static function () :void {
				} );

				return $process;
			}
		};
	}

	private function createRecordingEnvironmentResolver() :TestingEnvironmentResolver {
		return new class() extends TestingEnvironmentResolver {

			public bool $assertDockerReadyCalled = false;

			public function assertDockerReady( string $rootDir ) :void {
				$this->assertDockerReadyCalled = true;
			}
		};
	}

	/**
	 * @param int[] $exitCodes
	 */
	private function createRecordingDockerComposeExecutor( array $exitCodes ) :DockerComposeExecutor {
		return new class( $exitCodes ) extends DockerComposeExecutor {

			/** @var array<int,array{root_dir:string,compose_files:array,sub_command:array,env_overrides:?array}> */
			public array $calls = [];

			/** @var int[] */
			private array $exitCodes;

			/**
			 * @param int[] $exitCodes
			 */
			public function __construct( array $exitCodes ) {
				parent::__construct();
				$this->exitCodes = $exitCodes;
			}

			public function run(
				string $rootDir,
				array $composeFiles,
				array $subCommand,
				?array $envOverrides = null
			) :int {
				$this->calls[] = [
					'root_dir' => $rootDir,
					'compose_files' => $composeFiles,
					'sub_command' => $subCommand,
					'env_overrides' => $envOverrides,
				];

				return (int)( \array_shift( $this->exitCodes ) ?? 0 );
			}
		};
	}

	private function createFixedBashCommandResolver( string $command ) :BashCommandResolver {
		return new class( $command ) extends BashCommandResolver {

			private string $command;

			public function __construct( string $command ) {
				$this->command = $command;
			}

			public function resolve() :string {
				return $this->command;
			}
		};
	}
}

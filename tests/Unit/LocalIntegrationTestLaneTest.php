<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalWpTestsInstallerCommandBuilder;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class LocalIntegrationTestLaneTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
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
			$installerCommandBuilder
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
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'COMPOSE_PROJECT_NAME' => 'shield-local-db',
				'SHIELD_PACKAGE_PATH' => false,
			],
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
		foreach ( $processRunner->calls as $call ) {
			$this->assertSame(
				[
					'DOCKER_BUILDKIT' => '1',
					'MSYS_NO_PATHCONV' => '1',
					'COMPOSE_PROJECT_NAME' => 'shield-local-db',
					'SHIELD_PACKAGE_PATH' => false,
				],
				$call[ 'env_overrides' ]
			);
		}
	}

	public function testDbDownOnlyRunsComposeDownAndExits() :void {
		$processRunner = new RecordingProcessRunner();
		$environmentResolver = $this->createRecordingEnvironmentResolver();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 7 ] );

		$lane = new LocalIntegrationTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor
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

	private function createRecordingEnvironmentResolver() :TestingEnvironmentResolver {
		return new class() extends TestingEnvironmentResolver {

			public bool $assertDockerReadyCalled = false;

			public function assertDockerReady( string $rootDir ) :void {
				$this->assertDockerReadyCalled = true;
			}
		};
	}

	private function createRecordingInstallerCommandBuilder( array $command ) :LocalWpTestsInstallerCommandBuilder {
		return new class( $command ) extends LocalWpTestsInstallerCommandBuilder {

			/** @var array<int,array{db_name:string,db_user:string,db_pass:string,db_host:string,wp_version:string,skip_db_create:bool}> */
			public array $calls = [];

			/** @var string[] */
			private array $command;

			/**
			 * @param string[] $command
			 */
			public function __construct( array $command ) {
				parent::__construct();
				$this->command = $command;
			}

			public function build(
				string $dbName,
				string $dbUser,
				string $dbPass,
				string $dbHost,
				string $wpVersion,
				bool $skipDbCreate
			) :array {
				$this->calls[] = [
					'db_name' => $dbName,
					'db_user' => $dbUser,
					'db_pass' => $dbPass,
					'db_host' => $dbHost,
					'wp_version' => $wpVersion,
					'skip_db_create' => $skipDbCreate,
				];

				return $this->command;
			}
		};
	}
}

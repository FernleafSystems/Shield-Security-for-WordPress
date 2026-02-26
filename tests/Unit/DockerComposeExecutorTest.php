<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DockerComposeExecutorTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testRunBuildsExpectedComposeCommand() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0 ] );
		$executor = new DockerComposeExecutor( $processRunner );
		$envOverrides = [
			'COMPOSE_PROJECT_NAME' => 'shield-tests',
		];

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml', 'tests/docker/docker-compose.package.yml' ],
			[ 'up', '-d', 'mysql-latest' ],
			$envOverrides
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			[
				'docker',
				'compose',
				'-f',
				'tests/docker/docker-compose.yml',
				'-f',
				'tests/docker/docker-compose.package.yml',
				'up',
				'-d',
				'mysql-latest',
			],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( $envOverrides, $processRunner->calls[ 0 ][ 'env_overrides' ] );
	}

	public function testRunReturnsUnderlyingExitCode() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 9 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'build', 'test-runner-latest' ]
		);

		$this->assertSame( 9, $exitCode );
	}

	public function testRunIgnoringFailureDoesNotThrowOnNonZeroExitCode() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 5 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$executor->runIgnoringFailure(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'down', '-v', '--remove-orphans' ]
		);

		$this->assertCount( 1, $processRunner->calls );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
	}

	/**
	 * @param int[] $exitCodes
	 */
	private function createRecordingProcessRunner( array $exitCodes ) :ProcessRunner {
		return new class( $exitCodes ) extends ProcessRunner {

			/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
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
					'has_output_callback' => $onOutput !== null,
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
}

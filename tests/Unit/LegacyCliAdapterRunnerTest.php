<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\LegacyCliAdapterRunner;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LegacyCliAdapterRunnerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testUnknownArgumentReturnsErrorWithoutRunningSubprocess() :void {
		$processRunner = $this->createRecordingProcessRunner();
		$adapter = new LegacyCliAdapterRunner( $processRunner );
		$helpCalls = 0;

		$exitCode = $adapter->run(
			[ '--unknown-flag' ],
			$this->projectRoot,
			[
				'--source' => 'test:source',
			],
			'test:source',
			static function () use ( &$helpCalls ) :void {
				$helpCalls++;
			}
		);

		$this->assertSame( 1, $exitCode );
		$this->assertSame( 0, $helpCalls );
		$this->assertCount( 0, $processRunner->calls );
	}

	public function testMultipleModeFlagsReturnErrorWithoutRunningSubprocess() :void {
		$processRunner = $this->createRecordingProcessRunner();
		$adapter = new LegacyCliAdapterRunner( $processRunner );

		$exitCode = $adapter->run(
			[ '--source', '--package' ],
			$this->projectRoot,
			[
				'--source' => 'test:source',
				'--package' => 'test:package-full',
			],
			'test:source',
			static function () :void {
			}
		);

		$this->assertSame( 1, $exitCode );
		$this->assertCount( 0, $processRunner->calls );
	}

	public function testDefaultModeRoutesToConfiguredDefaultCommand() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 7 ] );
		$adapter = new LegacyCliAdapterRunner( $processRunner );

		$exitCode = $adapter->run(
			[],
			$this->projectRoot,
			[
				'--source' => 'test:source',
				'--package' => 'test:package-full',
			],
			'test:source',
			static function () :void {
			}
		);

		$this->assertSame( 7, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			[ \PHP_BINARY, './bin/shield', 'test:source' ],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( $this->projectRoot, $processRunner->calls[ 0 ][ 'working_dir' ] );
	}

	public function testExplicitModeRoutesToMappedCommand() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0 ] );
		$adapter = new LegacyCliAdapterRunner( $processRunner );

		$exitCode = $adapter->run(
			[ '--package' ],
			$this->projectRoot,
			[
				'--source' => 'analyze:source',
				'--package' => 'analyze:package',
			],
			'analyze:source',
			static function () :void {
			}
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			[ \PHP_BINARY, './bin/shield', 'analyze:package' ],
			$processRunner->calls[ 0 ][ 'command' ]
		);
	}

	public function testHelpShortCircuitsWithoutRunningSubprocess() :void {
		$processRunner = $this->createRecordingProcessRunner();
		$adapter = new LegacyCliAdapterRunner( $processRunner );
		$helpCalls = 0;

		$exitCode = $adapter->run(
			[ '--help' ],
			$this->projectRoot,
			[
				'--source' => 'test:source',
			],
			'test:source',
			static function () use ( &$helpCalls ) :void {
				$helpCalls++;
			}
		);

		$this->assertSame( 0, $exitCode );
		$this->assertSame( 1, $helpCalls );
		$this->assertCount( 0, $processRunner->calls );
	}

	/**
	 * @param int[] $exitCodes
	 */
	private function createRecordingProcessRunner( array $exitCodes = [ 0 ] ) :ProcessRunner {
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
}

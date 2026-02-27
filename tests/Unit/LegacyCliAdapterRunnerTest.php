<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\LegacyCliAdapterRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class LegacyCliAdapterRunnerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testUnknownArgumentReturnsErrorWithoutRunningSubprocess() :void {
		$processRunner = new RecordingProcessRunner();
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
		$processRunner = new RecordingProcessRunner();
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
		$processRunner = new RecordingProcessRunner( [ 7 ] );
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
		$processRunner = new RecordingProcessRunner( [ 0 ] );
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
		$processRunner = new RecordingProcessRunner();
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
}

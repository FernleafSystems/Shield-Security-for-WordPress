<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;

class ProcessRunnerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testRunStreamsNormalizedOutputToStdout() :void {
		$runner = new ProcessRunner();
		$this->expectOutputString(
			\implode( \PHP_EOL, [ 'line1', 'line2', 'line3', '' ] )
		);

		$process = $runner->run(
			[
				\PHP_BINARY,
				'-r',
				'echo "line1\rline2\r\nline3\n";',
			],
			$this->projectRoot
		);

		$this->assertSame( 0, $process->getExitCode() );
	}

	public function testRunReturnsProcessForNonZeroExitCodes() :void {
		$runner = new ProcessRunner();
		$process = $runner->run(
			[
				\PHP_BINARY,
				'-r',
				'echo "failing"; exit(7);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( 7, $process->getExitCode() );
		$this->assertStringContainsString( 'failing', $process->getOutput() );
	}

	public function testRunSupportsCustomOutputCallback() :void {
		$runner = new ProcessRunner();
		$outputTypes = [];
		$outputBuffers = [];

		$process = $runner->run(
			[
				\PHP_BINARY,
				'-r',
				'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(0);',
			],
			$this->projectRoot,
			static function ( string $type, string $buffer ) use ( &$outputTypes, &$outputBuffers ) :void {
				$outputTypes[] = $type;
				$outputBuffers[] = $buffer;
			}
		);

		$this->assertSame( 0, $process->getExitCode() );
		$this->assertContains( 'out', $outputBuffers );
		$this->assertContains( 'err', $outputBuffers );
		$this->assertCount( 2, $outputTypes );
	}

	public function testRunThrowsForMissingWorkingDirectory() :void {
		$runner = new ProcessRunner();
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Working directory does not exist' );

		$runner->run(
			[ \PHP_BINARY, '-r', 'echo "nope";' ],
			$this->projectRoot.'/missing-'.\uniqid()
		);
	}

	public function testRunOrThrowReturnsProcessForSuccessfulCommand() :void {
		$runner = new ProcessRunner();
		$process = $runner->runOrThrow(
			[
				\PHP_BINARY,
				'-r',
				'echo "ok"; exit(0);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( 0, $process->getExitCode() );
		$this->assertStringContainsString( 'ok', $process->getOutput() );
	}

	public function testRunOrThrowThrowsOnFailedCommandWithStderr() :void {
		$runner = new ProcessRunner();
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Command failed with exit code 3' );
		$this->expectExceptionMessage( 'Error output: boom' );

		$runner->runOrThrow(
			[
				\PHP_BINARY,
				'-r',
				'fwrite(STDERR, "boom"); exit(3);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);
	}
}

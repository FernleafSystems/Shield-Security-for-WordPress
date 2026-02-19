<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Unit tests for CommandRunner.
 * Focus: Test command building, binary resolution, and working directory management.
 */
class CommandRunnerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
	}

	private function createRunner( bool $silenceOutput = false ) :CommandRunner {
		$runner = new CommandRunner( $this->projectRoot, static function ( string $message ) :void {
		} );
		if ( $silenceOutput ) {
			$property = new \ReflectionProperty( CommandRunner::class, 'processRunner' );
			$property->setAccessible( true );
			$property->setValue( $runner, new class extends ProcessRunner {
				public function run( array $command, string $workingDir, ?callable $onOutput = null ) :Process {
					return parent::run( $command, $workingDir, $onOutput ?? static function () :void {
					} );
				}
			} );
		}
		return $runner;
	}

	// =========================================================================
	// getComposerCommand() - Composer resolution
	// =========================================================================

	/**
	 * Test that getComposerCommand returns a valid array
	 */
	public function testGetComposerCommandReturnsArray() :void {
		$runner = $this->createRunner();
		$command = $runner->getComposerCommand();

		$this->assertIsArray( $command );
		$this->assertNotEmpty( $command );
	}

	/**
	 * Test that composer command defaults to 'composer' when no env var set
	 */
	public function testComposerCommandDefaultsToComposer() :void {
		// Save and clear the env var
		$originalBinary = getenv( 'COMPOSER_BINARY' );
		putenv( 'COMPOSER_BINARY' );

		try {
			$runner = $this->createRunner();
			$command = $runner->getComposerCommand();

			// Should be either ['composer'] or ['php', 'something.phar']
			$this->assertTrue(
				$command[0] === 'composer' || ( isset( $command[1] ) && str_ends_with( $command[1], '.phar' ) ),
				'Composer command should default to "composer" or a php/phar combination'
			);
		}
		finally {
			// Restore env var
			if ( $originalBinary !== false ) {
				putenv( 'COMPOSER_BINARY='.$originalBinary );
			}
		}
	}

	// =========================================================================
	// Working directory validation
	// =========================================================================

	/**
	 * Test that run() throws exception for non-existent working directory
	 */
	public function testRunThrowsOnNonExistentWorkingDirectory() :void {
		$runner = $this->createRunner();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Working directory does not exist' );

		$runner->run( [ 'echo', 'test' ], '/path/that/does/not/exist/'.uniqid() );
	}

	/**
	 * Test that run() executes successfully with valid command
	 */
	public function testRunExecutesValidCommand() :void {
		$this->expectOutputRegex( '/test/' );
		$runner = $this->createRunner();

		// Use a simple command that works on all platforms
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$runner->run( [ 'cmd', '/c', 'echo', 'test' ], $this->projectRoot );
		}
		else {
			$runner->run( [ 'echo', 'test' ], $this->projectRoot );
		}
	}

	/**
	 * Test that run() throws exception on failed command
	 */
	public function testRunThrowsOnFailedCommand() :void {
		$runner = $this->createRunner();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Command failed with exit code' );

		// Use a command that will definitely fail
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$runner->run( [ 'cmd', '/c', 'exit', '1' ], $this->projectRoot );
		}
		else {
			$runner->run( [ 'false' ], $this->projectRoot );
		}
	}

	/**
	 * Test that run() preserves current working directory after execution
	 */
	public function testRunPreservesWorkingDirectory() :void {
		$this->expectOutputRegex( '/test/' );
		$runner = $this->createRunner();
		$originalCwd = getcwd();

		$tempDir = sys_get_temp_dir();

		if ( PHP_OS_FAMILY === 'Windows' ) {
			$runner->run( [ 'cmd', '/c', 'echo', 'test' ], $tempDir );
		}
		else {
			$runner->run( [ 'echo', 'test' ], $tempDir );
		}

		$this->assertEquals( $originalCwd, getcwd(), 'Working directory should be restored after command execution' );
	}

	/**
	 * Test that run() includes stderr output in exception message when command fails
	 */
	public function testRunIncludesStderrInException() :void {
		$runner = $this->createRunner( true );

		try {
			// Use PHP to write to stderr and exit with error - works on all platforms
			$runner->run( [
				PHP_BINARY,
				'-r',
				'fwrite(STDERR, "test stderr message"); exit(1);'
			], $this->projectRoot );

			$this->fail( 'Expected RuntimeException was not thrown' );
		}
		catch ( \RuntimeException $e ) {
			$message = $e->getMessage();

			// Verify exception contains expected parts
			$this->assertStringContainsString( 'Command failed with exit code', $message );
			$this->assertStringContainsString( 'test stderr message', $message );
			$this->assertStringContainsString( 'Error output:', $message );
		}
	}

	/**
	 * Test that run() normalizes carriage returns in streamed output.
	 */
	public function testRunNormalizesCarriageReturnsInOutput() :void {
		$runner = $this->createRunner();

		$this->expectOutputString(
			\implode( \PHP_EOL, [ 'line1', 'line2', 'line3', '' ] )
		);
		$runner->run( [
			PHP_BINARY,
			'-r',
			'echo "line1\rline2\r\nline3\n";'
		], $this->projectRoot );
	}
}

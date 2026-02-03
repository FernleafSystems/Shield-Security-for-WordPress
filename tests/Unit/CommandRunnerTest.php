<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner;
use PHPUnit\Framework\TestCase;

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

	private function createRunner() :CommandRunner {
		return new CommandRunner( $this->projectRoot, function ( string $message ) {} );
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
		$runner = $this->createRunner();

		// Use a simple command that works on all platforms
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$runner->run( [ 'cmd', '/c', 'echo', 'test' ], $this->projectRoot );
		}
		else {
			$runner->run( [ 'echo', 'test' ], $this->projectRoot );
		}

		$this->assertTrue( true ); // If we got here, command succeeded
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
}

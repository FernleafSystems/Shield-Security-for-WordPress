<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\GitPreCommitCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PreCommitChangedFileLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GitPreCommitCommandTest extends TestCase {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testCommandForwardsPositionalPaths() :void {
		$lane = new RecordingGitPreCommitLane();
		$command = new GitPreCommitCommand( __DIR__, $lane );

		$tester = new CommandTester( $command );
		$exitCode = $tester->execute( [
			'paths' => [ 'src/Foo.php', 'tests/Unit/FooTest.php' ],
		] );

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [
			'src/Foo.php',
			'tests/Unit/FooTest.php',
		], $lane->paths );
	}

	public function testCommandParsesNullDelimitedStdinThroughRealScript() :void {
		$this->skipIfPackageScriptUnavailable();

		$relativePath = 'tmp/pre-commit-stdin-invalid.php';
		$absolutePath = $this->getPluginFilePath( $relativePath );
		$dir = \dirname( $absolutePath );
		if ( !\is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}

		try {
			\file_put_contents( $absolutePath, "<?php declare( strict_types=1 )\n" );

			$process = $this->runProcess(
				[ \PHP_BINARY, $this->getPluginFilePath( 'bin/shield' ), 'git:pre-commit', '--stdin', '--null' ],
				[],
				$relativePath."\0"
			);

			$this->assertNotSame( 0, $process->getExitCode() ?? 0, $this->processOutput( $process ) );
		}
		finally {
			if ( \is_file( $absolutePath ) ) {
				\unlink( $absolutePath );
			}
			$remaining = \is_dir( $dir ) ? \scandir( $dir ) : false;
			if ( \is_array( $remaining ) && \count( \array_diff( $remaining, [ '.', '..' ] ) ) === 0 ) {
				\rmdir( $dir );
			}
		}
	}
}

class RecordingGitPreCommitLane extends PreCommitChangedFileLane {

	/** @var string[] */
	public array $paths = [];

	public function run( string $rootDir, array $paths ) :int {
		$this->paths = $paths;
		return 0;
	}
}

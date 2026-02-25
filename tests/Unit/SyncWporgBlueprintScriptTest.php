<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Basic safety checks for the WordPress.org blueprint sync script.
 */
class SyncWporgBlueprintScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testSyncScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/sync-wporg-blueprint.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame( 0, $returnCode, 'bin/sync-wporg-blueprint.php should have valid PHP syntax: '.\implode( "\n", $output ) );
	}

	public function testSyncScriptHelpShowsUsageAndCliOptions() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = $this->runSyncScript( [ '--help' ] );

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( 'Usage:', $output );
		$this->assertStringContainsString( '--svn-root', $output );
		$this->assertStringContainsString( '--source', $output );
		$this->assertStringContainsString( '--check-only', $output );
	}

	public function testSyncScriptRequiresSvnRoot() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = $this->runSyncScript( [] );

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'--svn-root is required',
			$process->getOutput().$process->getErrorOutput()
		);
	}

	public function testCheckOnlyModeReturnsExitTwoWhenDestinationMissing() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$svnRoot = $this->createTrackedTempDir( 'shield-sync-svn-root-' );
		\mkdir( Path::join( $svnRoot, 'trunk' ), 0777, true );
		\mkdir( Path::join( $svnRoot, 'tags' ), 0777, true );

		$process = $this->runSyncScript( [
			'--svn-root='.$svnRoot,
			'--check-only',
		] );

		$this->assertSame( 2, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Blueprint destination missing:',
			$process->getOutput().$process->getErrorOutput()
		);
	}

	/**
	 * @param string[] $args
	 */
	private function runSyncScript( array $args ) :Process {
		$command = \array_merge(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/sync-wporg-blueprint.php' ) ],
			$args
		);

		$process = new Process( $command, $this->getPluginRoot() );
		$process->run();
		return $process;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteDownCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteFixtureCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteResetCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteUpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteWpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestBrowserCleanupCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestDockerCleanupCommand;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupReport;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteRuntimeHostManifestProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceAssetBuildReadiness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceGeneratedConfigReadiness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

class BrowserSharedServiceAdmissionTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $rootDir;
	private string $otherRootDir;
	private string $lockDir;

	protected function setUp() :void {
		parent::setUp();
		$this->rootDir = $this->createTrackedTempDir( 'shield-browser-admission-root-' );
		$this->otherRootDir = $this->createTrackedTempDir( 'shield-browser-admission-other-root-' );
		$this->lockDir = $this->createTrackedTempDir( 'shield-browser-admission-locks-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testReentrantInstancesRetainAdmissionAcrossRootsAndNestedFailure() :void {
		$pool = new BrowserTestLanePool( $this->lockDir );
		$this->assertSame( 23, $pool->withSharedServiceAdmission( $this->rootDir, function () :int {
			$nestedPool = new BrowserTestLanePool( $this->lockDir );
			try {
				$nestedPool->withSharedServiceAdmission( $this->otherRootDir, function () :int {
					$this->assertContenderExitCode( 17 );
					throw new \RuntimeException( 'nested failure' );
				} );
			}
			catch ( \RuntimeException $exception ) {
				$this->assertSame( 'nested failure', $exception->getMessage() );
			}
			$this->assertContenderExitCode( 17 );
			return 23;
		} ) );
		$this->assertContenderExitCode( 0 );
	}

	public function testOuterFailureReleasesAdmissionWithoutRemovingLockFile() :void {
		try {
			( new BrowserTestLanePool( $this->lockDir ) )->withSharedServiceAdmission( $this->rootDir, static function () :int {
				throw new \RuntimeException( 'outer failure' );
			} );
			$this->fail( 'Expected the owner failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame( 'outer failure', $exception->getMessage() );
		}
		$this->assertFileExists( $this->lockDir.'/shared-services.lock' );
		$this->assertContenderExitCode( 0 );
	}

	public function testLifecycleRetainsAdmissionDuringSweepPreparationConsumerAndCleanup() :void {
		$stageExitCodes = [];
		$probe = function ( string $stage ) use ( &$stageExitCodes ) :void {
			$stageExitCodes[ $stage ] = $this->runContender()->getExitCode();
		};
		$sweeper = $this->getMockBuilder( DockerResourceSweeper::class )
			->onlyMethods( [ 'startupSweep', 'cleanupRunResources' ] )->getMock();
		$sweeper->expects( $this->once() )->method( 'startupSweep' )->willReturnCallback( static function () use ( $probe ) :void {
			$probe( 'startup' );
		} );
		$sweeper->expects( $this->once() )->method( 'cleanupRunResources' )->willReturnCallback( static function () use ( $probe ) :DockerCleanupReport {
			$probe( 'cleanup' );
			$report = new DockerCleanupReport();
			$report->addFinding( 'cleanup failure' );
			return $report;
		} );
		$manager = $this->getMockBuilder( LocalSiteManager::class )->disableOriginalConstructor()
			->onlyMethods( [ 'prepareBrowserLane', 'ensureSharedDatabaseReady', 'cleanupCentralBrowserFixture' ] )->getMock();
		$manager->expects( $this->once() )->method( 'prepareBrowserLane' )->willReturnCallback( static function () use ( $probe ) :int {
			$probe( 'preparation' );
			return 0;
		} );
		$manager->expects( $this->exactly( 2 ) )->method( 'cleanupCentralBrowserFixture' );
		$pool = new BrowserTestLanePool( $this->lockDir );
		$lane = $this->buildLane( $pool, $manager, $sweeper );
		$result = $this->silenced( function () use ( $lane, $probe ) :int {
			return $lane->runWithConsumer( $this->rootDir, function ( array $map ) use ( $probe ) :int {
				$this->assertCount( 1, $map );
				$probe( 'consumer' );
				throw new \RuntimeException( 'consumer failure' );
			}, [ 'lanes' => '1', 'mode' => 'warm' ] );
		} );
		$this->assertSame( 1, $result );
		$this->assertSame( [ 'startup' => 17, 'preparation' => 17, 'consumer' => 17, 'cleanup' => 17 ], $stageExitCodes );
		$this->assertContenderExitCode( 0 );
		$lease = $pool->acquire( $this->rootDir, static function () :void {}, [], 1 );
		$this->assertSame( 1, $lease->laneIndex() );
		$lease->release();
	}

	public function testReadinessFailureReleasesAdmissionBeforeAnyLanePreparation() :void {
		$sweeper = $this->createMock( DockerResourceSweeper::class );
		$sweeper->expects( $this->once() )->method( 'startupSweep' );
		$sweeper->expects( $this->never() )->method( 'cleanupRunResources' );
		$manager = $this->createMock( LocalSiteManager::class );
		$manager->expects( $this->never() )->method( 'prepareBrowserLane' );
		$readiness = $this->createMock( \FernleafSystems\ShieldPlatform\Tooling\Testing\SourceGeneratedConfigReadiness::class );
		$readiness->expects( $this->once() )->method( 'ensureReady' )->willThrowException( new \RuntimeException( 'config failure' ) );
		$lane = new BrowserTestLane( new RecordingProcessRunner(), $manager, new BrowserTestLanePool( $this->lockDir ), new RecordingLocalSiteRuntimeHostManifestProvider(), $readiness, new RecordingSourceAssetBuildReadiness(), $sweeper );
		$this->assertSame( 1, $this->silenced( fn() :int => $lane->runWithConsumer( $this->rootDir, static function () :int { return 0; }, [ 'lanes' => '1' ] ) ) );
		$this->assertContenderExitCode( 0 );
	}

	public function testListRunSkipsAdmission() :void {
		$pool = $this->getMockBuilder( BrowserTestLanePool::class )->onlyMethods( [ 'withSharedServiceAdmission' ] )->getMock();
		$pool->expects( $this->never() )->method( 'withSharedServiceAdmission' );
		$manager = $this->createMock( LocalSiteManager::class );
		$sweeper = $this->createMock( DockerResourceSweeper::class );
		$sweeper->expects( $this->never() )->method( 'startupSweep' );
		$this->assertSame( 0, $this->silenced( fn() :int => $this->buildLane( $pool, $manager, $sweeper )->run( $this->rootDir, [ '--list' ] ) ) );
	}

	public function testBrowserCleanupRetainsAdmissionForDockerAndWorkspaceCleanup() :void {
		$sweeper = $this->createMock( DockerResourceSweeper::class );
		$sweeper->expects( $this->once() )->method( 'cleanupRunResources' )->willReturnCallback( function () :DockerCleanupReport {
			$this->assertContenderExitCode( 17 );
			return new DockerCleanupReport();
		} );
		$refresher = $this->createMock( LocalSiteRuntimeRefresher::class );
		$refresher->expects( $this->once() )->method( 'cleanupStaleWorkspaces' )->willReturnCallback( function () :array {
			$this->assertContenderExitCode( 17 );
			return [];
		} );
		$tester = new CommandTester( new TestBrowserCleanupCommand( $this->rootDir, $sweeper, $refresher, new BrowserTestLanePool( $this->lockDir ) ) );
		$this->assertSame( 0, $tester->execute( [ '--all' => true ] ), $tester->getDisplay() );
		$this->assertContenderExitCode( 0 );
	}

	public function testDockerBrowserCleanupUsesAdmissionAndOtherScopesSkipIt() :void {
		$runner = $this->getMockBuilder( ProcessRunner::class )->onlyMethods( [ 'run' ] )->getMock();
		$scripted = new ScriptedProcessRunner( [
			[ 'stdout' => '' ], [ 'stdout' => '' ], [ 'stdout' => '' ],
			[ 'stdout' => '' ], [ 'stdout' => '' ],
			[ 'stdout' => '[{"Labels":{"com.fernleaf.harness":"shield-plugin-browser"}}]' ],
		] );
		$firstCall = true;
		$runner->method( 'run' )->willReturnCallback( function ( array $command, string $rootDir, ?callable $onOutput, ?array $env ) use ( $scripted, &$firstCall ) :Process {
			if ( $firstCall ) {
				$this->assertContenderExitCode( 17 );
				$firstCall = false;
			}
			return $scripted->run( $command, $rootDir, $onOutput, $env );
		} );
		$tester = new CommandTester( new TestDockerCleanupCommand( $this->rootDir, $runner, new BrowserTestLanePool( $this->lockDir ) ) );
		$this->assertSame( 0, $tester->execute( [ '--scope' => 'browser', '--dry-run' => true ] ), $tester->getDisplay() );
		$this->assertFalse( $firstCall );
		$this->assertContenderExitCode( 0 );

		$pool = $this->getMockBuilder( BrowserTestLanePool::class )->onlyMethods( [ 'withSharedServiceAdmission' ] )->getMock();
		$pool->expects( $this->never() )->method( 'withSharedServiceAdmission' );
		$tester = new CommandTester( new TestDockerCleanupCommand( $this->rootDir, new ScriptedProcessRunner( [] ), $pool ) );
		$this->assertSame( 0, $tester->execute( [ '--scope' => 'source', '--dry-run' => true ] ), $tester->getDisplay() );
	}

	/** @dataProvider siteCommandProfiles */
	public function testSiteMutatorsAdmitOnlyBrowserProfiles( string $commandClass, string $method, array $arguments, string $profile ) :void {
		$manager = $this->getMockBuilder( LocalSiteManager::class )->disableOriginalConstructor()
			->onlyMethods( [ 'definition', $method ] )->getMock();
		$manager->method( 'definition' )->willReturn( $profile === 'browser' ? LocalSiteDefinitions::browserLane( 1 ) : ( $profile === 'dev' ? LocalSiteDefinitions::dev() : LocalSiteDefinitions::test() ) );
		$manager->expects( $this->once() )->method( $method )->willReturnCallback( function () use ( $profile, $method ) {
			if ( $profile === 'browser' ) {
				$this->assertContenderExitCode( 17 );
			}
			return $method === 'wpCapture' ? [ 'stdout' => '{}', 'stderr' => '' ] : 0;
		} );
		$pool = $profile === 'browser' ? new BrowserTestLanePool( $this->lockDir )
			: $this->getMockBuilder( BrowserTestLanePool::class )->onlyMethods( [ 'withSharedServiceAdmission' ] )->getMock();
		if ( $profile !== 'browser' ) {
			$pool->expects( $this->never() )->method( 'withSharedServiceAdmission' );
		}
		$tester = new CommandTester( new $commandClass( 'test:site:operation', 'Site operation', $this->rootDir, $manager, $pool ) );
		$this->assertSame( 0, $tester->execute( $arguments ), $tester->getDisplay() );
		$this->assertContenderExitCode( 0 );
	}

	public static function siteCommandProfiles() :array {
		$cases = [];
		foreach ( [ 'browser', 'test', 'dev' ] as $profile ) {
			foreach ( [
				[ SiteUpCommand::class, 'up', [] ],
				[ SiteDownCommand::class, 'down', [] ],
				[ SiteResetCommand::class, 'reset', [] ],
				[ SiteWpCommand::class, 'wp', [ 'wp_cli_args' => [ 'plugin', 'list' ] ] ],
				[ SiteFixtureCommand::class, 'wpCapture', [ 'fixture' => 'actions-queue', 'fixture_action' => 'seed' ] ],
			] as $case ) {
				$cases[ $profile.'-'.$case[ 1 ] ] = \array_merge( $case, [ $profile ] );
			}
		}
		return $cases;
	}

	/** @dataProvider siteCommandProfiles */
	public function testSiteOperationFailureReturnsFailureAndReleasesAdmission( string $commandClass, string $method, array $arguments, string $profile ) :void {
		$manager = $this->getMockBuilder( LocalSiteManager::class )->disableOriginalConstructor()
			->onlyMethods( [ 'definition', $method ] )->getMock();
		$manager->method( 'definition' )->willReturn( $profile === 'browser' ? LocalSiteDefinitions::browserLane( 1 ) : ( $profile === 'dev' ? LocalSiteDefinitions::dev() : LocalSiteDefinitions::test() ) );
		$manager->expects( $this->once() )->method( $method )->willThrowException( new \RuntimeException( 'operation failure' ) );
		$tester = new CommandTester( new $commandClass( 'test:site:operation', 'Site operation', $this->rootDir, $manager, new BrowserTestLanePool( $this->lockDir ) ) );
		$this->assertSame( 1, $tester->execute( $arguments ) );
		$this->assertStringContainsString( 'operation failure', $tester->getDisplay() );
		$this->assertContenderExitCode( 0 );
	}

	private function buildLane( BrowserTestLanePool $pool, LocalSiteManager $manager, DockerResourceSweeper $sweeper ) :BrowserTestLane {
		return new BrowserTestLane( new RecordingProcessRunner(), $manager, $pool, new RecordingLocalSiteRuntimeHostManifestProvider(), new RecordingSourceGeneratedConfigReadiness(), new RecordingSourceAssetBuildReadiness(), $sweeper );
	}

	private function assertContenderExitCode( int $expected ) :void {
		$process = $this->runContender();
		$this->assertSame( $expected, $process->getExitCode(), $process->getErrorOutput() );
	}

	private function runContender() :Process {
		$code = <<<'PHP'
require $argv[3];
$pool = new \FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool($argv[2]);
try {
    exit($pool->withSharedServiceAdmission($argv[1], static function ():int { return 0; }, static function ():void {}));
}
catch (\RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(17);
}
PHP;
		$process = new Process( [ \PHP_BINARY, '-r', $code, $this->otherRootDir, $this->lockDir, \dirname( __DIR__, 2 ).'/vendor/autoload.php' ], $this->otherRootDir, [ 'SHIELD_BROWSER_LANE_WAIT_SECONDS' => '1' ] );
		$process->setTimeout( 10 );
		$process->run();
		return $process;
	}

	/** @param callable():int $callback */
	private function silenced( callable $callback ) :int {
		\ob_start();
		try {
			return $callback();
		}
		finally {
			\ob_end_clean();
		}
	}
}

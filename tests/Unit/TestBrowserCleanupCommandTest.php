<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestBrowserCleanupCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupReport;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestBrowserCleanupCommandTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testDryRunPassesThroughToCleanupServices() :void {
		$resourceSweeper = new RecordingCleanupCommandSweeper();
		$runtimeRefresher = new RecordingCleanupCommandRuntimeRefresher();
		$tester = new CommandTester( new TestBrowserCleanupCommand(
			$this->createTrackedTempDir( 'shield-cleanup-command-' ),
			$resourceSweeper,
			$runtimeRefresher,
			new FixedCleanupCommandLanePool( 2 )
		) );

		$exitCode = $tester->execute( [
			'--dry-run' => true,
			'--all' => true,
			'--lanes' => '3',
			'--runtime-workspace-max-age-hours' => '5',
		] );

		$this->assertSame( 0, $exitCode, $tester->getDisplay() );
		$this->assertSame( [ 'lane_count' => 3, 'full_cleanup' => true, 'dry_run' => true ], $resourceSweeper->lastCall );
		$this->assertSame( [ 'older_than_seconds' => 0, 'dry_run' => true ], $runtimeRefresher->lastCall );
		$this->assertStringContainsString( 'Browser cleanup dry-run plan:', $tester->getDisplay() );
	}

	public function testCleanupCommandExposesDryRunOption() :void {
		$command = new TestBrowserCleanupCommand( $this->createTrackedTempDir( 'shield-cleanup-command-definition-' ) );

		$this->assertTrue( $command->getDefinition()->hasOption( 'dry-run' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'all' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'lanes' ) );
	}
}

class RecordingCleanupCommandSweeper extends DockerResourceSweeper {

	/** @var array{lane_count:int,full_cleanup:bool,dry_run:bool}|null */
	public ?array $lastCall = null;

	public function cleanupRunResources( string $rootDir, string $runId, int $laneCount, bool $fullCleanup, bool $dryRun = false ) :DockerCleanupReport {
		$this->lastCall = [
			'lane_count' => $laneCount,
			'full_cleanup' => $fullCleanup,
			'dry_run' => $dryRun,
		];
		$report = new DockerCleanupReport( $dryRun );
		$report->addPlannedAction( 'remove test container' );

		return $report;
	}
}

class RecordingCleanupCommandRuntimeRefresher extends LocalSiteRuntimeRefresher {

	/** @var array{older_than_seconds:int,dry_run:bool}|null */
	public ?array $lastCall = null;

	public function cleanupStaleWorkspaces( string $rootDir, int $olderThanSeconds = 86400, bool $dryRun = false ) :array {
		$this->lastCall = [
			'older_than_seconds' => $olderThanSeconds,
			'dry_run' => $dryRun,
		];

		return [ 'tmp/.browser-runtime-refresh/stale' ];
	}
}

class FixedCleanupCommandLanePool extends BrowserTestLanePool {

	private int $count;

	public function __construct( int $count ) {
		$this->count = $count;
	}

	public function laneCount( ?int $laneCountOverride = null ) :int {
		return $laneCountOverride ?? $this->count;
	}
}

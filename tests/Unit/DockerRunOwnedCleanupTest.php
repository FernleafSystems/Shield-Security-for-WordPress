<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerHarnessLabels;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcess;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DockerRunOwnedCleanupTest extends TestCase {

	use TempDirLifecycleTrait;

	/** @var array<string,string|false> */
	private array $originalEnvironment = [];

	private string $root;

	protected function setUp() :void {
		parent::setUp();
		$this->root = $this->createTrackedTempDir( 'shield-owned-docker-' );
		foreach ( [ 'DOCKER_CONTEXT', 'DOCKER_HOST', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH' ] as $key ) {
			$this->originalEnvironment[ $key ] = \getenv( $key );
			\putenv( $key );
		}
	}

	protected function tearDown() :void {
		foreach ( $this->originalEnvironment as $key => $value ) {
			$value === false ? \putenv( $key ) : \putenv( $key.'='.$value );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testSnapshotAndCleanupDiscoverPartialAllocationAndPreserveEveryForeignResource() :void {
		$runner = new OwnedDockerProcessRunner();
		foreach ( [ 'container', 'volume', 'network' ] as $type ) {
			$runner->add( $type, 'owned-'.$type, $this->labels( 'mine', 'transient' ) );
			$runner->add( $type, 'expired-'.$type, $this->labels( 'other', 'transient', false ) );
			$runner->add( $type, 'malformed-'.$type, [ DockerHarnessLabels::HARNESS => LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE ] );
			$runner->add( $type, 'foreign-'.$type, $this->labels( 'other', 'transient' ) );
		}
		$runner->add( 'volume', 'reusable', $this->labels( 'mine', 'reusable' ) );
		$runner->add( 'container', 'legacy', [] );
		$sweeper = $this->bound( $runner );
		$snapshot = $sweeper->snapshotRunResources( $this->root, 'mine' );
		$this->assertSame( 'daemon-a', $snapshot[ 'daemonId' ] );
		$this->assertCount( 3, $snapshot[ 'resources' ] );
		$this->assertSame( [], $sweeper->cleanupOwnedRunResources( $this->root, 'mine', [ $snapshot[ 'resources' ][ 0 ] ] )->findings() );
		$this->assertCount( 11, $runner->allResources() );
		$this->assertSame( [], $sweeper->snapshotRunResources( $this->root, 'mine' )[ 'resources' ] );
		$this->assertSame( [], $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $snapshot[ 'resources' ] )->findings() );
		$this->assertCount( 3, $runner->removed );
	}

	/** @dataProvider ordinaryCleanupPhases */
	public function testOrdinaryStartupWarmAndFullPoliciesRemainUnchanged( string $phase, int $remaining ) :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'container', 'owned', $this->labels( 'mine', 'transient' ) );
		$runner->add( 'container', 'expired', $this->labels( 'other', 'transient', false ) );
		$runner->add( 'container', 'malformed', [ DockerHarnessLabels::HARNESS => LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE ] );
		$runner->add( 'container', 'foreign', $this->labels( 'other', 'transient' ) );
		$runner->add( 'container', LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME, [] );
		$runner->add( 'volume', 'reusable', $this->labels( 'other', 'reusable' ) );
		$sweeper = new DockerResourceSweeper( $runner );
		if ( $phase === 'startup' ) {
			$sweeper->startupSweep( $this->root );
		}
		else {
			$this->assertSame( [], $sweeper->cleanupRunResources( $this->root, 'mine', 1, $phase === 'full' )->findings() );
		}
		$this->assertCount( $remaining, $runner->allResources() );
	}

	/** @return array<string,array{0:string,1:int}> */
	public static function ordinaryCleanupPhases() :array {
		return [ 'startup' => [ 'startup', 3 ], 'warm' => [ 'warm', 2 ], 'full' => [ 'full', 0 ] ];
	}

	public function testFailedInspectionRetainsSelectiveCandidatesButNotFullCleanupTargets() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'container', 'expired', $this->labels( 'other', 'transient', false ) );
		$runner->inspectError = 'Error response from daemon: i/o timeout';
		$sweeper = new DockerResourceSweeper( $runner );
		$this->assertContains( 'Docker cleanup command failed (1): docker container inspect '.$id.' STDERR: Error response from daemon: i/o timeout', $sweeper->cleanupRunResources( $this->root, 'mine', 1, false )->findings() );
		$this->assertSame( [], $runner->removed );
		$sweeper->cleanupRunResources( $this->root, 'mine', 1, true );
		$this->assertSame( [ [ 'type' => 'container', 'id' => $id ] ], $runner->removed );
	}

	public function testChangedRecordedOwnershipRefusesAllDeletion() :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'container', 'owned', $this->labels( 'mine', 'transient' ) );
		$id = $runner->add( 'volume', 'recorded', $this->labels( 'other', 'transient' ) );
		$report = $this->bound( $runner )->cleanupOwnedRunResources( $this->root, 'mine', [ [ 'type' => 'volume', 'id' => $id ] ] );
		$this->assertTrue( $report->hasFindings() );
		$this->assertSame( [], $runner->removed );
		$this->assertCount( 2, $runner->allResources() );
	}

	public function testOwnershipIsRecheckedImmediatelyBeforeDeletion() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'volume', 'changes', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$inspections = 0;
		$runner->before = function ( array $command ) use ( $runner, $id, &$inspections ) :void {
			if ( $command === [ 'docker', 'volume', 'inspect', $id ] && ++$inspections === 3 ) {
				$runner->resources[ 'volume' ][ $id ][ 'Labels' ][ DockerHarnessLabels::RUN_ID ] = 'other';
			}
		};
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->hasFindings() );
		$this->assertSame( [], $runner->removed );
	}

	public function testChangedOrUnavailableDaemonCannotManufactureMissingResources() :void {
		$runner = new OwnedDockerProcessRunner();
		$sweeper = $this->bound( $runner );
		$runner->daemonId = 'daemon-b';
		$recorded = [ [ 'type' => 'volume', 'id' => 'missing' ] ];
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $recorded )->hasFindings() );
		$this->assertSame( [], $runner->removed );
		$runner->daemonId = '';
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $recorded )->hasFindings() );
		$runner->daemonId = 'daemon-a';
		$this->assertSame( [], $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $recorded )->findings() );
	}

	public function testRecoveryBindingRejectsDifferentDaemonBeforeResourceInspection() :void {
		$runner = new OwnedDockerProcessRunner();
		$sweeper = new DockerResourceSweeper( $runner, null, true );
		try {
			$sweeper->bindDockerDaemon( $this->root, 'daemon-old' );
			$this->fail( 'A different daemon must not admit recovery.' );
		}
		catch ( \RuntimeException $error ) {
			$this->assertStringContainsString( 'identity changed', $error->getMessage() );
		}
		$this->assertFalse( $runner->resourceInspectionOccurred() );
	}

	public function testDaemonDriftBetweenOwnershipInspectionAndRemovalRefusesDeletion() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'volume', 'changes', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$inspections = 0;
		$runner->before = static function ( array $command ) use ( $runner, $id, &$inspections ) :void {
			if ( $command === [ 'docker', 'volume', 'inspect', $id ] && ++$inspections === 3 ) {
				$runner->daemonId = 'daemon-b';
			}
		};
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->hasFindings() );
		$this->assertSame( [], $runner->removed );
	}

	public function testUncertainAbsenceReportsOriginalErrorAndNeverSettlesReceipt() :void {
		$runner = new OwnedDockerProcessRunner();
		$sweeper = $this->bound( $runner );
		$recorded = [ [ 'type' => 'volume', 'id' => 'missing' ] ];
		$runner->inspectExitCode = 125;
		foreach ( [ 'TLS certificate file not found: C:\certs\key.pem', 'Error response from daemon: No such volume: missing-other' ] as $error ) {
			$runner->inspectError = $error;
			$findings = \implode( "\n", $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $recorded )->findings() );
			$this->assertStringContainsString( 'Docker cleanup command failed (125): docker volume inspect missing STDERR: '.$error, $findings );
		}
		$this->assertSame( [], $runner->removed );
		$runner->inspectError = null;
		$this->assertSame( [], $sweeper->cleanupOwnedRunResources( $this->root, 'mine', $recorded )->findings() );
	}

	public function testFailedLiveInspectionReportsOriginalErrorAndRetainsResource() :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'volume', 'mine-volume', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$runner->inspectExitCode = 125;
		$runner->inspectError = 'permission denied while trying to connect to the Docker daemon';
		$findings = \implode( "\n", $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->findings() );
		$this->assertStringContainsString( 'Docker cleanup command failed (125): docker volume inspect mine-volume STDERR: '.$runner->inspectError, $findings );
		$this->assertSame( [], $runner->removed );
		$this->assertCount( 1, $runner->allResources() );
	}

	public function testLaunchExceptionsReachReportWithOriginalMessage() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'volume', 'mine-volume', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$runner->before = static function ( array $command ) :void {
			if ( ( $command[ 2 ] ?? '' ) === 'rm' ) {
				throw new \RuntimeException( 'CreateProcess failed: docker.exe vanished' );
			}
		};
		$findings = \implode( "\n", $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->findings() );
		$this->assertStringContainsString( 'failed to start: remove owned volume '.$id.' (docker volume rm '.$id.'): CreateProcess failed: docker.exe vanished', $findings );
		$runner->before = static function ( array $command ) :void {
			if ( ( $command[ 1 ] ?? '' ) === 'info' ) {
				throw new \RuntimeException( 'CreateProcess failed: docker.exe missing' );
			}
		};
		$this->assertContains( 'CreateProcess failed: docker.exe missing', $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->findings() );
		$this->assertSame( [], $runner->removed );
		$this->assertCount( 1, $runner->allResources() );
	}

	public function testTransportIsFrozenForSweeperAndAllocationChildren() :void {
		\putenv( 'DOCKER_CONTEXT=selected' );
		$runner = new OwnedDockerProcessRunner();
		$sweeper = new DockerResourceSweeper( $runner, null, true );
		$binding = $sweeper->bindDockerDaemon( $this->root );
		$this->assertSame( 'npipe:////./pipe/selected-engine', $binding[ 'environment' ][ 'DOCKER_HOST' ] );
		$this->assertFalse( $binding[ 'environment' ][ 'DOCKER_CONTEXT' ] );
		\putenv( 'DOCKER_CONTEXT=changed' );
		\putenv( 'DOCKER_HOST=tcp://changed:2375' );
		$sweeper->startupSweep( $this->root );
		$labels = $sweeper->labelEnvironment( 'mine', 'transient', 'lane-2', \gmdate( \DATE_ATOM, \time()+3600 ), 'mine', 'reusable', \gmdate( \DATE_ATOM, \time()+86400 ) );
		$this->assertSame( $binding[ 'environment' ][ 'DOCKER_HOST' ], $labels[ 'DOCKER_HOST' ] );
		$this->assertFalse( $labels[ 'DOCKER_CONTEXT' ] );
		$this->assertSame( 'mine', $labels[ 'SHIELD_BROWSER_CONTAINER_RUN_ID' ] );
		$this->assertSame( 'reusable', $labels[ 'SHIELD_BROWSER_VOLUME_LIFECYCLE' ] );
		foreach ( $runner->calls as $call ) {
			if ( ( $call[ 'command' ][ 1 ] ?? '' ) !== 'context' ) {
				$this->assertSame( $binding[ 'environment' ], $call[ 'env_overrides' ] );
			}
		}
	}

	/** @dataProvider occupiedSlotLabels */
	public function testStartupRefusesOccupiedSharedContainerWithoutWrites( array $labels ) :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'container', LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME, $labels );
		$sweeper = $this->bound( $runner );
		try {
			$sweeper->startupSweep( $this->root );
			$this->fail( 'A foreign fixed container must not be replaced by Compose.' );
		}
		catch ( \RuntimeException $error ) {
			$this->assertStringContainsString( 'incomplete', $error->getMessage() );
		}
		$this->assertSame( [], $runner->removed );
		$this->assertFalse( $runner->composeOccurred() );
		$this->assertCount( 1, $runner->allResources() );
	}

	/** @return array<string,array{0:array<string,string>}> */
	public static function occupiedSlotLabels() :array {
		$harness = LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE;
		$valid = [ DockerHarnessLabels::HARNESS => $harness, DockerHarnessLabels::RUN_ID => 'other', DockerHarnessLabels::LIFECYCLE => 'transient', DockerHarnessLabels::EXPIRES_AT => \gmdate( \DATE_ATOM, \time()+3600 ) ];
		return [ 'foreign' => [ $valid ], 'unlabelled' => [ [] ], 'malformed' => [ [ DockerHarnessLabels::HARNESS => $harness ] ], 'expired' => [ \array_merge( $valid, [ DockerHarnessLabels::EXPIRES_AT => '2000-01-01T00:00:00Z' ] ) ] ];
	}

	public function testSelectedLaneGuardPrecedesAllocationAndPreservesOtherLanes() :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'container', 'shield-test-site-lane-2-wordpress-1', $this->labels( 'other', 'transient' ) );
		$runner->add( 'container', 'shield-test-site-lane-3-wordpress-1', $this->labels( 'other', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$sweeper->startupSweep( $this->root );
		$sweeper->labelEnvironment( 'mine', 'transient', 'lane-1', 'future', 'mine', 'reusable', 'future' );
		try {
			$sweeper->labelEnvironment( 'mine', 'transient', 'lane-2', 'future', 'mine', 'reusable', 'future' );
			$this->fail( 'The selected lane must refuse its foreign occupant.' );
		}
		catch ( \RuntimeException $error ) {
			$this->assertStringContainsString( 'incomplete', $error->getMessage() );
		}
		$this->assertCount( 2, $runner->allResources() );
		$this->assertFalse( $runner->composeOccurred() );
	}

	public function testStartupAllowsSupportedReusableVolumesWithoutRelabelling() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'volume', LocalSiteDefinitions::BROWSER_DB_VOLUME_NAME, $this->labels( 'old-run', 'reusable' ) );
		$before = $runner->resources[ 'volume' ][ $id ];
		$sweeper = $this->bound( $runner );
		$sweeper->startupSweep( $this->root );
		$sweeper->labelEnvironment( 'mine', 'transient', 'lane-1', 'future', 'mine', 'reusable', 'future' );
		$this->assertSame( $before, $runner->resources[ 'volume' ][ $id ] );
		$this->assertSame( [], $runner->removed );
	}

	public function testLaterLaneMayUseOwnSharedServicesWithoutReplacingForeignSlots() :void {
		$runner = new OwnedDockerProcessRunner();
		$sweeper = $this->bound( $runner );
		$sweeper->startupSweep( $this->root );
		$runner->add( 'container', LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME, $this->labels( 'mine', 'transient' ) );
		$runner->add( 'network', LocalSiteDefinitions::BROWSER_NETWORK_NAME, $this->labels( 'mine', 'transient' ) );
		$before = $runner->allResources();
		$sweeper->labelEnvironment( 'mine', 'transient', 'lane-2', 'future', 'mine', 'reusable', 'future' );
		$this->assertSame( $before, $runner->allResources() );
		$this->assertSame( [], $runner->removed );
	}

	public function testIncompleteDeletionAndDryRunRemainTruthful() :void {
		$runner = new OwnedDockerProcessRunner();
		$id = $runner->add( 'volume', 'mine-volume', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$report = $sweeper->cleanupOwnedRunResources( $this->root, 'mine', [], true );
		$this->assertSame( [], $report->findings() );
		$this->assertCount( 1, $report->plannedActions() );
		$this->assertSame( [], $runner->removed );
		$runner->refuseRemoval = $id;
		$findings = \implode( "\n", $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->findings() );
		$this->assertStringContainsString( 'Docker cleanup command failed (1): docker volume rm '.$id.' STDERR: Removal refused.', $findings );
		$this->assertCount( 1, $runner->allResources() );
		$runner->refuseRemoval = null;
		$this->assertSame( [], $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->findings() );
	}

	public function testFullCleanupAndUnboundOperationsAreRefusedWithoutWrites() :void {
		$runner = new OwnedDockerProcessRunner();
		$sweeper = new DockerResourceSweeper( $runner, null, true );
		$this->assertTrue( $sweeper->cleanupRunResources( $this->root, 'mine', 1, true )->hasFindings() );
		$this->assertTrue( $sweeper->cleanupAllHarnessResources( $this->root, 1 )->hasFindings() );
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->hasFindings() );
		$this->assertSame( [], $runner->calls );
	}

	public function testOwnedInspectionRequiresOneRowWhileOrdinaryInspectionUsesFirstRow() :void {
		$runner = new OwnedDockerProcessRunner();
		$runner->add( 'volume', 'mine-volume', $this->labels( 'mine', 'transient' ) );
		$sweeper = $this->bound( $runner );
		$runner->duplicateInspectRow = true;
		$this->assertTrue( $sweeper->cleanupOwnedRunResources( $this->root, 'mine' )->hasFindings() );
		$this->assertSame( [], $runner->removed );
		$this->assertSame( [], ( new DockerResourceSweeper( $runner ) )->cleanupRunResources( $this->root, 'mine', 1, false )->findings() );
		$this->assertCount( 1, $runner->removed );
	}

	private function bound( OwnedDockerProcessRunner $runner ) :DockerResourceSweeper {
		$sweeper = new DockerResourceSweeper( $runner, null, true );
		$sweeper->bindDockerDaemon( $this->root, 'daemon-a' );
		return $sweeper;
	}

	/** @return array<string,string> */
	private function labels( string $runId, string $lifecycle, bool $future = true ) :array {
		return [ DockerHarnessLabels::HARNESS => LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE, DockerHarnessLabels::RUN_ID => $runId, DockerHarnessLabels::LIFECYCLE => $lifecycle, DockerHarnessLabels::EXPIRES_AT => \gmdate( \DATE_ATOM, \time()+( $future ? 3600 : -3600 ) ) ];
	}
}

/** Stateful Docker boundary; tests exercise native selection, validation and cleanup owners. */
class OwnedDockerProcessRunner extends ScriptedProcessRunner {

	public string $daemonId = 'daemon-a';

	/** @var array<string,array<string,array<string,mixed>>> */
	public array $resources = [ 'container' => [], 'volume' => [], 'network' => [] ];

	/** @var list<array{type:string,id:string}> */
	public array $removed = [];

	public ?string $refuseRemoval = null;

	public ?string $inspectError = null;

	public int $inspectExitCode = 1;

	public bool $duplicateInspectRow = false;

	/** @var callable|null */
	public $before = null;

	public function __construct() {
		parent::__construct( [] );
	}

	/** @param array<string,string> $labels */
	public function add( string $type, string $name, array $labels ) :string {
		$id = $type === 'volume' ? $name : \hash( 'sha256', $type.':'.$name );
		$data = [ 'Id' => $id, 'Name' => $name, 'Labels' => $labels ];
		if ( $type === 'container' ) {
			$data[ 'Config' ] = [ 'Labels' => $labels ];
		}
		$this->resources[ $type ][ $id ] = $data;
		return $id;
	}

	public function run( array $command, string $workingDir, ?callable $onOutput = null, ?array $envOverrides = null ) :Process {
		$this->calls[] = [ 'command' => $command, 'working_dir' => $workingDir, 'env_overrides' => $envOverrides, 'has_output_callback' => $onOutput !== null ];
		if ( $this->before !== null ) {
			( $this->before )( $command );
		}
		if ( ( $command[ 1 ] ?? '' ) === 'compose' ) {
			return new ScriptedProcess( 0 );
		}
		if ( $command === [ 'docker', 'context', 'show' ] ) {
			return new ScriptedProcess( 0, 'selected' );
		}
		if ( \array_slice( $command, 0, 3 ) === [ 'docker', 'context', 'inspect' ] ) {
			return new ScriptedProcess( 0, (string)\json_encode( [ [ 'Endpoints' => [ 'docker' => [ 'Host' => 'npipe:////./pipe/selected-engine', 'SkipTLSVerify' => false ] ], 'TLSMaterial' => [] ] ] ) );
		}
		if ( $command === [ 'docker', 'info', '--format', '{{.ID}}' ] ) {
			return new ScriptedProcess( 0, $this->daemonId );
		}
		$type = $command[ 1 ] ?? '';
		$operation = $command[ 2 ] ?? '';
		if ( !isset( $this->resources[ $type ] ) ) {
			throw new \RuntimeException( 'Unexpected Docker boundary operation.' );
		}
		if ( $operation === 'ls' ) {
			$lines = [];
			foreach ( $this->resources[ $type ] as $id => $data ) {
				if ( \in_array( '--filter', $command, true ) && ( $data[ 'Labels' ][ DockerHarnessLabels::HARNESS ] ?? null ) !== LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE ) {
					continue;
				}
				$lines[] = \in_array( '--format', $command, true ) && $type === 'container' ? $id."\t".$data[ 'Name' ] : $id;
			}
			return new ScriptedProcess( 0, \implode( "\n", $lines ) );
		}
		$id = (string)\end( $command );
		if ( $operation === 'inspect' && $this->inspectError !== null ) {
			return new ScriptedProcess( $this->inspectExitCode, '', $this->inspectError );
		}
		$key = null;
		foreach ( $this->resources[ $type ] as $resourceId => $data ) {
			if ( $resourceId === $id || $data[ 'Name' ] === $id || ( $type !== 'volume' && \strpos( $resourceId, $id ) === 0 ) ) {
				$key = $resourceId;
				break;
			}
		}
		if ( $key === null ) {
			return new ScriptedProcess( 1, '', 'Error response from daemon: No such '.$type.': '.$id );
		}
		if ( $operation === 'inspect' ) {
			$data = $this->resources[ $type ][ $key ];
			return new ScriptedProcess( 0, (string)\json_encode( $this->duplicateInspectRow ? [ $data, $data ] : [ $data ] ) );
		}
		if ( $operation === 'rm' ) {
			if ( $key === $this->refuseRemoval ) {
				return new ScriptedProcess( 1, '', 'Removal refused.' );
			}
			$this->removed[] = [ 'type' => $type, 'id' => $key ];
			unset( $this->resources[ $type ][ $key ] );
			return new ScriptedProcess( 0, $key );
		}
		throw new \RuntimeException( 'Unexpected Docker boundary operation.' );
	}

	/** @return list<array<string,mixed>> */
	public function allResources() :array {
		return \array_values( \array_merge( ...\array_values( $this->resources ) ) );
	}

	public function resourceInspectionOccurred() :bool {
		foreach ( $this->calls as $call ) {
			if ( ( $call[ 'command' ][ 1 ] ?? '' ) !== 'context' && ( $call[ 'command' ][ 2 ] ?? '' ) === 'inspect' ) {
				return true;
			}
		}
		return false;
	}

	public function composeOccurred() :bool {
		foreach ( $this->calls as $call ) {
			if ( ( $call[ 'command' ][ 1 ] ?? '' ) === 'compose' ) {
				return true;
			}
		}
		return false;
	}
}

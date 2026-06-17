<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupPolicy;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcessRunner;
use PHPUnit\Framework\TestCase;

class DockerResourceSweeperTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testListFailureBecomesCleanupFinding() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 1, 'stderr' => 'Docker unavailable' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-list-fail-' ),
			'run-1',
			1,
			false
		);

		$this->assertTrue( $this->contains( $report->findings(), 'docker container ls' ) );
		$this->assertTrue( $report->hasFindings() );
	}

	public function testInvalidInspectJsonBecomesCleanupFinding() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => "container-1\n" ],
			[ 'exit_code' => 0, 'stdout' => 'not-json' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-inspect-json-' ),
			'run-1',
			1,
			false
		);

		$this->assertTrue( $this->contains( $report->findings(), 'invalid inspect JSON: docker container inspect container-1' ) );
	}

	public function testInvalidLegacyInspectJsonBecomesCleanupFinding() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => 'not-json' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-legacy-inspect-json-' ),
			'run-1',
			1,
			false
		);

		$this->assertTrue( $this->contains( $report->findings(), 'invalid inspect JSON: docker network inspect '.LocalSiteDefinitions::BROWSER_NETWORK_NAME ) );
	}

	public function testRemoveFailureBecomesCleanupFinding() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => "container-1\n" ],
			[ 'exit_code' => 0, 'stdout' => $this->inspectJson( 'transient', 'run-1', \gmdate( \DATE_ATOM, \time() + 3600 ) ) ],
			[ 'exit_code' => 1, 'stderr' => 'remove denied' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-remove-fail-' ),
			'run-1',
			1,
			false
		);

		$this->assertTrue( $this->contains( $report->findings(), 'docker container rm -f container-1' ) );
		$this->assertTrue( $this->contains( $report->findings(), 'remove denied' ) );
	}

	public function testDryRunPlansRemovalsWithoutRunningDestructiveCommands() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => "container-1\n" ],
			[ 'exit_code' => 0, 'stdout' => $this->inspectJson( 'transient', 'run-1', \gmdate( \DATE_ATOM, \time() + 3600 ) ) ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-dry-run-' ),
			'run-1',
			1,
			false,
			true
		);

		$this->assertTrue( $report->dryRun() );
		$this->assertTrue( $this->contains( $report->plannedActions(), 'remove container container-1' ) );
		$this->assertFalse( $this->commandWasRun( $runner, [ 'docker', 'container', 'rm' ] ) );
	}

	public function testComposeDownFailureBecomesCleanupFinding() :void {
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 1, 'stderr' => 'compose failed' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-compose-fail-' ),
			'run-1',
			1,
			true
		);

		$this->assertTrue( $this->contains( $report->findings(), 'docker compose' ) );
		$this->assertTrue( $this->contains( $report->findings(), 'compose failed' ) );
	}

	public function testWarmCleanupPreservesValidReusableVolume() :void {
		$volumeInspect = $this->inspectJson( 'reusable', 'run-1', \gmdate( \DATE_ATOM, \time() + 86400 ) );
		$runner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => "volume-1\n" ],
			[ 'exit_code' => 0, 'stdout' => $volumeInspect ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => "volume-1\n" ],
			[ 'exit_code' => 0, 'stdout' => $volumeInspect ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
			[ 'exit_code' => 1, 'stderr' => 'No such network' ],
		] );

		$report = ( new DockerResourceSweeper( $runner ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-warm-volume-' ),
			'run-current',
			1,
			false
		);

		$this->assertSame( [], $report->findings() );
		$this->assertFalse( $this->commandWasRun( $runner, [ 'docker', 'volume', 'rm' ] ) );
	}

	public function testCustomPolicyUsesConfiguredHarnessLabel() :void {
		$runner = new ScriptedProcessRunner( [] );

		( new DockerResourceSweeper( $runner, DockerCleanupPolicy::crossSite() ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-cross-site-label-' ),
			'run-1',
			1,
			false
		);

		$this->assertFalse( $this->commandWasRun( $runner, [ 'docker', 'container', 'ls', '-a', '--format' ] ) );
		foreach ( $runner->calls as $call ) {
			if ( \array_slice( $call[ 'command' ], 0, 3 ) !== [ 'docker', 'container', 'ls' ]
				 && \array_slice( $call[ 'command' ], 0, 3 ) !== [ 'docker', 'volume', 'ls' ]
				 && \array_slice( $call[ 'command' ], 0, 3 ) !== [ 'docker', 'network', 'ls' ] ) {
				continue;
			}
			$this->assertContains(
				'label=com.fernleaf.harness=shield-plugin-cross-site',
				$call[ 'command' ]
			);
		}
	}

	public function testCustomPolicyFullCleanupUsesPolicyComposeProject() :void {
		$runner = new ScriptedProcessRunner( [] );

		( new DockerResourceSweeper( $runner, DockerCleanupPolicy::crossSite() ) )->cleanupRunResources(
			$this->createTrackedTempDir( 'shield-sweeper-cross-site-compose-' ),
			'run-1',
			1,
			true
		);

		$this->assertTrue( $this->commandWasRun( $runner, [
			'docker',
			'compose',
			'-p',
			'shield-cross-site',
			'-f',
			'tests/docker/docker-compose.cross-site.yml',
			'down',
			'-v',
			'--remove-orphans',
		] ) );
	}

	private function inspectJson( string $lifecycle, string $runId, string $expiresAt, string $harness = LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE ) :string {
		return \json_encode( [
			[
				'Name' => 'resource-name',
				'Config' => [
					'Labels' => [
						'com.fernleaf.harness' => $harness,
						'com.fernleaf.lifecycle' => $lifecycle,
						'com.fernleaf.run-id' => $runId,
						'com.fernleaf.expires-at' => $expiresAt,
					],
				],
			],
		], \JSON_UNESCAPED_SLASHES ) ?: '[]';
	}

	/**
	 * @param string[] $haystack
	 */
	private function contains( array $haystack, string $needle ) :bool {
		foreach ( $haystack as $value ) {
			if ( \strpos( $value, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $prefix
	 */
	private function commandWasRun( ScriptedProcessRunner $runner, array $prefix ) :bool {
		foreach ( $runner->calls as $call ) {
			if ( \array_slice( $call[ 'command' ], 0, \count( $prefix ) ) === $prefix ) {
				return true;
			}
		}

		return false;
	}
}

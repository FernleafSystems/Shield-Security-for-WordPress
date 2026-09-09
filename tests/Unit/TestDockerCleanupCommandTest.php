<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestDockerCleanupCommand;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptedProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestDockerCleanupCommandTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testDryRunUsesExplicitScopePolicy() :void {
		$processRunner = new ScriptedProcessRunner( [
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
			[ 'exit_code' => 0, 'stdout' => '' ],
		] );
		$tester = new CommandTester( new TestDockerCleanupCommand(
			$this->createTrackedTempDir( 'shield-docker-cleanup-command-' ),
			$processRunner
		) );

		$exitCode = $tester->execute( [
			'--scope' => 'cross-site',
			'--all' => true,
			'--dry-run' => true,
			'--lanes' => '2',
		] );

		$this->assertSame( 0, $exitCode, $tester->getDisplay() );
		$this->assertStringContainsString( 'Docker cleanup dry-run plan for cross-site:', $tester->getDisplay() );
		$this->assertStringContainsString( 'docker compose -p shield-cross-site -f tests/docker/docker-compose.cross-site.yml down -v --remove-orphans', $tester->getDisplay() );
		$this->assertCount( 3, $processRunner->calls );
		$this->assertContains( 'label=com.fernleaf.harness=shield-plugin-cross-site', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testScopeIsRequired() :void {
		$tester = new CommandTester( new TestDockerCleanupCommand(
			$this->createTrackedTempDir( 'shield-docker-cleanup-command-missing-scope-' ),
			new ScriptedProcessRunner( [] )
		) );

		$exitCode = $tester->execute( [] );

		$this->assertSame( 1, $exitCode );
		$this->assertStringContainsString( '--scope is required', $tester->getDisplay() );
	}

	public function testNonBrowserScopeDoesNotReadBrowserLaneEnvironment() :void {
		$originalLaneCount = \getenv( 'SHIELD_BROWSER_LANE_COUNT' );
		\putenv( 'SHIELD_BROWSER_LANE_COUNT=invalid' );

		try {
			$processRunner = new ScriptedProcessRunner( [
				[ 'exit_code' => 0, 'stdout' => '' ],
				[ 'exit_code' => 0, 'stdout' => '' ],
				[ 'exit_code' => 0, 'stdout' => '' ],
			] );
			$tester = new CommandTester( new TestDockerCleanupCommand(
				$this->createTrackedTempDir( 'shield-docker-cleanup-command-non-browser-scope-' ),
				$processRunner
			) );

			$exitCode = $tester->execute( [
				'--scope' => 'source',
				'--all' => true,
				'--dry-run' => true,
			] );

			$this->assertSame( 0, $exitCode, $tester->getDisplay() );
			$this->assertStringContainsString( 'Docker cleanup dry-run plan for source:', $tester->getDisplay() );
			$this->assertContains( 'label=com.fernleaf.harness=shield-plugin-source', $processRunner->calls[ 0 ][ 'command' ] );
		}
		finally {
			if ( $originalLaneCount === false ) {
				\putenv( 'SHIELD_BROWSER_LANE_COUNT' );
			}
			else {
				\putenv( 'SHIELD_BROWSER_LANE_COUNT='.$originalLaneCount );
			}
		}
	}

	public function testCommandExposesAuditOptions() :void {
		$command = new TestDockerCleanupCommand( $this->createTrackedTempDir( 'shield-docker-cleanup-command-definition-' ) );

		$this->assertTrue( $command->getDefinition()->hasOption( 'scope' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'dry-run' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'all' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'lanes' ) );
	}
}

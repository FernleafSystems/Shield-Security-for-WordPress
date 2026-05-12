<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteFixtureCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SiteFixtureCommandTest extends TestCase {

	public function test_execute_prints_the_final_json_payload_from_captured_output() :void {
		$siteManager = $this->buildSiteManagerMock();
		$siteManager->expects( $this->once() )
			->method( 'wpCapture' )
			->with(
				'/project-root',
				[
					'eval-file',
					'/app/tests/browser/support/run-runtime-fixture.php',
					'--',
					'actions-queue',
					'seed',
					'direct_table',
				]
			)
			->willReturn( [
				'stdout' => "runtime-noise\n{\"scenario\":\"direct_table\"}\n",
				'stderr' => "fixture-warning\n",
			] );

		$tester = new CommandTester(
			new SiteFixtureCommand(
				'test:site:fixture',
				'Fixture runner',
				'/project-root',
				$siteManager
			)
		);

		$exitCode = $tester->execute( [
			'fixture'        => 'actions-queue',
			'fixture_action' => 'seed',
			'fixture_args'   => [ '', 'direct_table', '' ],
		] );

		$this->assertSame( Command::SUCCESS, $exitCode );
		$this->assertJsonStringEqualsJsonString(
			'{"scenario":"direct_table"}',
			\trim( $tester->getDisplay() )
		);
	}

	public function test_execute_fails_when_captured_output_has_no_json_payload() :void {
		$siteManager = $this->buildSiteManagerMock();
		$siteManager->expects( $this->once() )
			->method( 'wpCapture' )
			->willReturn( [
				'stdout' => "runtime-noise\nstill-not-json\n",
				'stderr' => '',
			] );

		$tester = new CommandTester(
			new SiteFixtureCommand(
				'test:site:fixture',
				'Fixture runner',
				'/project-root',
				$siteManager
			)
		);

		$exitCode = $tester->execute( [
			'fixture'        => 'actions-queue',
			'fixture_action' => 'inspect',
			'fixture_args'   => [ 'direct_table' ],
		] );

		$this->assertSame( Command::FAILURE, $exitCode );
		$this->assertStringContainsString( 'Fixture command did not return a JSON payload.', $tester->getDisplay() );
	}

	private function buildSiteManagerMock() :LocalSiteManager {
		return $this->getMockBuilder( LocalSiteManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'wpCapture' ] )
			->getMock();
	}
}

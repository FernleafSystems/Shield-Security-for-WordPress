<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestBrowserCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestCrossSiteCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestIntegrationLocalCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageFullCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;

class ShieldCliCommandTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testShieldCliScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/shield' );
	}

	public function testHelpListsAllCanonicalCommands() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		foreach (
			[
				'dev:site:up',
				'dev:site:down',
				'dev:site:wp',
				'dev:site:reset',
				'dev:site:status',
				'test:site:up',
				'test:site:down',
				'test:site:wp',
				'test:site:fixture',
				'test:site:reset',
				'test:site:status',
				'test:browser',
				'test:cross-site',
				'test:source',
				'test:integration-local',
				'test:package-targeted',
				'test:package-full',
				'analyze:tooling',
				'analyze:source',
				'analyze:package',
			] as $commandName
		) {
			$this->assertStringContainsString( $commandName, $output );
		}
	}

	/**
	 * @dataProvider providerCommandNames
	 */
	public function testEachCommandProvidesHelp( string $commandName ) :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ $commandName, '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( $commandName, $this->processOutput( $process ) );
	}

	public function testPackageTargetedHelpIncludesStrictSkipOptions() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:package-targeted', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( '--fail-on-skipped', $output );
		$this->assertStringContainsString( '--no-fail-on-skipped', $output );
	}

	public function testIntegrationLocalCommandIncludesDebuggingOptions() :void {
		$this->skipIfPackageScriptUnavailable();
		$command = new TestIntegrationLocalCommand(
			$this->getPluginRoot(),
			$this->createMock( LocalIntegrationTestLane::class )
		);
		$this->assertTrue( $command->getDefinition()->hasOption( 'db-down' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'show-docker-output' ) );
	}

	public function testSourceCommandIncludesDebuggingOptions() :void {
		$this->skipIfPackageScriptUnavailable();
		$command = new TestSourceCommand(
			$this->getPluginRoot(),
			$this->createMock( SourceRuntimeTestLane::class )
		);
		$this->assertTrue( $command->getDefinition()->hasOption( 'refresh-setup' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'show-docker-output' ) );
	}

	public function testPackageFullCommandIncludesDebuggingOption() :void {
		$this->skipIfPackageScriptUnavailable();
		$command = new TestPackageFullCommand(
			$this->getPluginRoot(),
			$this->createMock( PackageFullTestLane::class )
		);
		$this->assertTrue( $command->getDefinition()->hasOption( 'show-docker-output' ) );
	}

	public function testBrowserCommandHelpIncludesPlaywrightForwardingHint() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:browser', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:browser', $output );
		$this->assertStringContainsString( 'composer: -- -- --headed', $output );
		$this->assertStringContainsString( '--clean', $output );
		$this->assertStringContainsString( '--warm', $output );
		$this->assertStringContainsString( '--show-setup-output', $output );
		$this->assertStringContainsString( '--lanes', $output );
	}

	public function testBrowserCommandIncludesHarnessOptions() :void {
		$this->skipIfPackageScriptUnavailable();
		$command = new TestBrowserCommand(
			$this->getPluginRoot(),
			$this->createMock( BrowserTestLane::class )
		);

		$this->assertTrue( $command->getDefinition()->hasOption( 'clean' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'warm' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'show-setup-output' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'lanes' ) );
	}

	public function testCrossSiteCommandHelpIncludesHarnessOptions() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:cross-site', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:cross-site', $output );
		$this->assertStringContainsString( '--clean', $output );
		$this->assertStringContainsString( '--warm', $output );
		$this->assertStringContainsString( '--show-setup-output', $output );
	}

	public function testCrossSiteCommandIncludesHarnessOptions() :void {
		$this->skipIfPackageScriptUnavailable();
		$command = new TestCrossSiteCommand(
			$this->getPluginRoot(),
			$this->createMock( CrossSiteTestLane::class )
		);

		$this->assertTrue( $command->getDefinition()->hasOption( 'clean' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'warm' ) );
		$this->assertTrue( $command->getDefinition()->hasOption( 'show-setup-output' ) );
	}

	public function testDevSiteWpHelpIncludesWpCliForwardingHint() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'dev:site:wp', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'dev:site:wp', $output );
		$this->assertStringContainsString( 'plugin list', $output );
	}

	public function testTestSiteWpHelpIncludesWpCliForwardingHint() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:site:wp', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:site:wp', $output );
		$this->assertStringContainsString( 'plugin list', $output );
	}

	public function testTestSiteFixtureHelpIncludesFixtureForwardingHint() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:site:fixture', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:site:fixture', $output );
		$this->assertStringContainsString( 'actions-queue seed direct_table', $output );
	}

	public function testAnalyzeSourceHelpIncludesRefreshSetupOption() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'analyze:source', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( '--refresh-setup', $this->processOutput( $process ) );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function providerCommandNames() :array {
		return [
			'test-source' => [ 'test:source' ],
			'test-browser' => [ 'test:browser' ],
			'test-cross-site' => [ 'test:cross-site' ],
			'test-integration-local' => [ 'test:integration-local' ],
			'dev-site-up' => [ 'dev:site:up' ],
			'dev-site-down' => [ 'dev:site:down' ],
			'dev-site-wp' => [ 'dev:site:wp' ],
			'dev-site-reset' => [ 'dev:site:reset' ],
			'dev-site-status' => [ 'dev:site:status' ],
			'test-site-up' => [ 'test:site:up' ],
			'test-site-down' => [ 'test:site:down' ],
			'test-site-wp' => [ 'test:site:wp' ],
			'test-site-fixture' => [ 'test:site:fixture' ],
			'test-site-reset' => [ 'test:site:reset' ],
			'test-site-status' => [ 'test:site:status' ],
			'test-package-targeted' => [ 'test:package-targeted' ],
			'test-package-full' => [ 'test:package-full' ],
			'analyze-tooling' => [ 'analyze:tooling' ],
			'analyze-source' => [ 'analyze:source' ],
			'analyze-package' => [ 'analyze:package' ],
		];
	}
}

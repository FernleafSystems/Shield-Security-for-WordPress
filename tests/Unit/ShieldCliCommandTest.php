<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

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

	public function testIntegrationLocalHelpIncludesDbDownOption() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:integration-local', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:integration-local', $output );
		$this->assertStringContainsString( '--db-down', $output );
		$this->assertStringContainsString( 'composer: -- -- --filter FooTest', $output );
	}

	public function testSourceCommandHelpIncludesRefreshSetupOption() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:source', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( '--refresh-setup', $this->processOutput( $process ) );
	}

	public function testBrowserCommandHelpIncludesPlaywrightForwardingHint() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:browser', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'test:browser', $output );
		$this->assertStringContainsString( 'composer: -- -- --headed', $output );
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

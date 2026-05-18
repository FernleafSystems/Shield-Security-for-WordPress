<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

class PowerTestToolingContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testComposerScriptsExposeOnlyTheSupportedTestingSurface() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$unitCommands = $this->getComposerScriptCommands( 'test:unit' );
		$this->assertContains( '@build:config', $unitCommands );
		$this->assertContains( '@php bin/run-unit-tests.php --runner-mode=auto', $unitCommands );

		$integrationCommands = $this->getComposerScriptCommands( 'test:integration' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $integrationCommands );
		$this->assertContains( '@build:config', $integrationCommands );
		$this->assertContains( '@php bin/shield test:integration-local', $integrationCommands );

		$browserCommands = $this->getComposerScriptCommands( 'test:browser' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $browserCommands );
		$this->assertContains( '@php bin/shield test:browser', $browserCommands );

		$crossSiteCommands = $this->getComposerScriptCommands( 'test:cross-site' );
		$this->assertSame( [ '@php bin/shield test:cross-site' ], $crossSiteCommands );
		$this->assertNotContains( '@test:cross-site', $this->getComposerScriptCommands( 'test' ) );

		$packageCommands = $this->getComposerScriptCommands( 'test:package' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $packageCommands );
		$this->assertContains( '@php bin/shield test:package-targeted', $packageCommands );

		$upgradePublicCommands = $this->getComposerScriptCommands( 'test:upgrade-public' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $upgradePublicCommands );
		$this->assertContains( '@php bin/shield test:upgrade-public', $upgradePublicCommands );

		$analyzeCommands = $this->getComposerScriptCommands( 'analyze' );
		$this->assertSame( [ '@php bin/shield analyze:source' ], $analyzeCommands );

		$composer = $this->decodePluginJsonFile( 'composer.json', 'composer.json' );
		$scripts = $composer[ 'scripts' ] ?? [];
		$this->assertIsArray( $scripts );

		foreach ( [
			'test:fast',
			'test:unit:serial',
			'test:unit:parallel',
			'test:unit:verify-power',
			'test:passkeys',
			'test:source',
			'test:integration:local',
			'test:package-targeted',
			'test:package-full',
			'analyze:source',
			'analyze:package',
			'playground:local',
			'playground:local:check',
			'playground:package:check',
			'playground:local:clean',
		] as $removedScript ) {
			$this->assertArrayNotHasKey( $removedScript, $scripts );
		}
	}

	public function testLocalIntegrationLaneRemainsSerialPhpUnit() :void {
		$lane = new LocalIntegrationTestLane();
		$reflection = new \ReflectionClass( $lane );
		$method = $reflection->getMethod( 'buildPhpUnitCommand' );
		$method->setAccessible( true );

		$command = $method->invoke( $lane, [ '--filter', 'InfrastructureSmokeTest' ] );
		$this->assertIsArray( $command );
		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'paratest', $command );
		$this->assertContains( 'phpunit-integration.xml', $command );
	}

	public function testCrossSiteWorkflowRunsCleanLaneWithScopedTriggers() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'GitHub workflows are excluded from packages (development-only)' );
		}

		$workflow = $this->getPluginFileContents(
			'.github/workflows/cross-site-tests.yml',
			'cross-site workflow'
		);

		$this->assertStringContainsString( 'workflow_dispatch:', $workflow );
		$this->assertStringContainsString( 'schedule:', $workflow );
		$this->assertStringContainsString( "cron: '45 6 * * 1-5'", $workflow );
		$this->assertStringContainsString( 'composer test:cross-site -- --clean', $workflow );

		foreach ( [
			'composer.json',
			'composer.lock',
			'src/ActionRouter/Actions/PluginImportExport_*.php',
			'src/Modules/Plugin/Lib/ImportExport/**',
			'src/WpCli/**',
			'infrastructure/src/Tooling/Cli/**',
			'infrastructure/src/Tooling/Testing/**',
			'tests/Helpers/CrossSite/**',
			'tests/docker/**',
			'.github/workflows/cross-site-tests.yml',
		] as $pathTrigger ) {
			$this->assertStringContainsString( $pathTrigger, $workflow );
		}

		$this->assertStringNotContainsString( 'MainWP', $workflow );
		$this->assertStringNotContainsString( 'mainwp', $workflow );
	}

	public function testCiPathFiltersExposeRequiredGateGroups() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'GitHub workflows are excluded from packages (development-only)' );
		}

		$filters = $this->getPluginFileContents(
			'.github/ci-path-filters.yml',
			'CI path filters'
		);

		foreach ( [
			'php:',
			'js:',
			'package:',
			'browser:',
			'ci_scripts:',
			"'src/**/*.php'",
			"'tests/docker/**'",
			"'assets/js/**'",
			"'src/ActionRouter/**'",
			"'.github/workflows/browser-tests.yml'",
		] as $contract ) {
			$this->assertStringContainsString( $contract, $filters );
		}

		$phpFilter = $this->extractCiPathFilterBlock( $filters, 'php', 'js' );
		$this->assertStringContainsString(
			"'.github/scripts/verify-docker-test-scripts.sh'",
			$phpFilter
		);

		$packageFilter = $this->extractCiPathFilterBlock( $filters, 'package', 'browser' );
		foreach ( [
			"'.github/scripts/verify-admin-bundle-safety.sh'",
			"'.github/scripts/read-packager-config.sh'",
		] as $contract ) {
			$this->assertStringContainsString( $contract, $packageFilter );
		}
	}

	public function testRequiredTestsWorkflowUsesExplicitParityCommands() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'GitHub workflows are excluded from packages (development-only)' );
		}

		$workflow = $this->getPluginFileContents(
			'.github/workflows/tests.yml',
			'tests workflow'
		);

		$this->assertStringContainsString( 'changes:', $workflow );
		$this->assertStringContainsString( 'uses: dorny/paths-filter@v4', $workflow );
		$this->assertStringContainsString( 'fetch-depth: 0', $workflow );
		$this->assertStringContainsString( 'filters: .github/ci-path-filters.yml', $workflow );
		$this->assertStringContainsString( 'pull-requests: read', $workflow );
		foreach ( [
			'php: ${{ steps.filter.outputs.php }}',
			'js: ${{ steps.filter.outputs.js }}',
			'package: ${{ steps.filter.outputs.package }}',
			'ci_scripts: ${{ steps.filter.outputs.ci_scripts }}',
			"needs.changes.outputs.php == 'true' || github.event_name == 'workflow_dispatch'",
			"needs.changes.outputs.js == 'true' || github.event_name == 'workflow_dispatch'",
			"needs.changes.outputs.package == 'true' || github.event_name == 'workflow_dispatch'",
			"needs.changes.outputs.ci_scripts == 'true' || github.event_name == 'workflow_dispatch'",
			"always() && (needs.changes.outputs.package == 'true' || github.event_name == 'workflow_dispatch') && needs.build-package.result == 'success'",
		] as $contract ) {
			$this->assertStringContainsString( $contract, $workflow );
		}

		$this->assertStringContainsString( 'run: composer analyze', $workflow );
		$this->assertStringNotContainsString( 'run: composer build:config', $workflow );
		$this->assertStringContainsString(
			'run: php bin/shield test:source --skip-unit-tests --show-docker-output',
			$workflow
		);
		$this->assertStringNotContainsString( 'SHIELD_SKIP_UNIT_TESTS:', $workflow );
	}

	public function testBrowserWorkflowRunsPathGatedDevelopPushesWithTwoLanes() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'GitHub workflows are excluded from packages (development-only)' );
		}

		$workflow = $this->getPluginFileContents(
			'.github/workflows/browser-tests.yml',
			'browser workflow'
		);

		foreach ( [
			"push:\n    branches: [ develop ]",
			'workflow_dispatch:',
			'schedule:',
			"cron: '30 6 * * 1-5'",
			'uses: dorny/paths-filter@v4',
			'fetch-depth: 0',
			'filters: .github/ci-path-filters.yml',
			"needs.changes.outputs.browser == 'true'",
			'npm run playwright:install -- --with-deps --only-shell',
			'composer test:browser -- --clean --lanes=2 -- --workers=2',
			'shield-test-site-lane-${lane}',
			'shield-browser-db',
		] as $contract ) {
			$this->assertStringContainsString( $contract, $workflow );
		}

		$this->assertStringNotContainsString( 'paths:', $workflow );
		$this->assertStringNotContainsString( 'matrix:', $workflow );
		$this->assertStringNotContainsString( '--shard', $workflow );
	}

	public function testIntegrationBootstrapFailsFastWhenParallelTokensArePresent() :void {
		$bootstrapPath = \dirname( __DIR__, 2 ).'/tests/Integration/bootstrap.php';
		$process = new Process( [
			\PHP_BINARY,
			'-r',
			"putenv('TEST_TOKEN=1'); require '".\str_replace( '\\', '\\\\', $bootstrapPath )."';",
		], \dirname( __DIR__, 2 ) );

		$process->run();
		$this->assertSame( 1, $process->getExitCode() );
		$this->assertStringContainsString( 'integration tests are serial-only', \strtolower( $process->getErrorOutput().$process->getOutput() ) );
	}

	private function extractCiPathFilterBlock( string $filters, string $filterName, string $nextFilterName ) :string {
		$startMarker = $filterName.":\n";
		$start = \strpos( $filters, $startMarker );
		if ( $start === false ) {
			$this->fail( \sprintf( 'CI path filter "%s" should exist.', $filterName ) );
		}

		$end = \strpos( $filters, "\n".$nextFilterName.":\n", $start );
		if ( $end === false ) {
			$this->fail( \sprintf( 'CI path filter "%s" should appear after "%s".', $nextFilterName, $filterName ) );
		}

		return \substr( $filters, $start, $end - $start );
	}
}

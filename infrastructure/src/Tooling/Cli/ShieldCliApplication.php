<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzePackageCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeToolingCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteDownCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteResetCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteStatusCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteUpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteWpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestBrowserCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestIntegrationLocalCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageFullCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageTargetedCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackagePathResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageTargetedTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\ToolingAnalysisLane;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

class ShieldCliApplication {

	private Application $application;

	/**
	 * @param null|array<string,callable():Command> $commandFactories
	 */
	public function __construct( string $projectRoot, ?array $commandFactories = null ) {
		$this->application = new Application( 'Shield Tooling CLI' );
		$this->application->setAutoExit( false );
		$this->application->setCommandLoader(
			new FactoryCommandLoader( $commandFactories ?? $this->buildCommandFactories( $projectRoot ) )
		);
	}

	/**
	 * @return array<string,callable():Command>
	 */
	private function buildCommandFactories( string $projectRoot ) :array {
		$environmentResolver = new TestingEnvironmentResolver();
		$packagePathResolver = new PackagePathResolver();
		$devSiteManager = new LocalSiteManager( LocalSiteDefinitions::dev(), null, $environmentResolver );
		$testSiteManager = new LocalSiteManager( LocalSiteDefinitions::test(), null, $environmentResolver );

		return \array_merge(
			$this->buildSiteCommandFactories( $projectRoot, 'dev:site', $devSiteManager, [
				'up' => 'Start or reuse the local Docker WordPress dev site for Shield source development.',
				'down' => 'Stop the local Docker WordPress dev site while preserving its persistent state.',
				'reset' => 'Destroy the persistent dev-site state and reprovision a fresh local Docker WordPress dev site.',
				'status' => 'Report whether the local Docker WordPress dev site is reachable and ready for Shield development.',
				'wp' => 'Run a WP-CLI command against the local Shield dev site after ensuring it is ready.',
			] ),
			$this->buildSiteCommandFactories( $projectRoot, 'test:site', $testSiteManager, [
				'up' => 'Start or reuse the isolated local Docker WordPress test site for Shield browser testing.',
				'down' => 'Stop the isolated local Docker WordPress test site while preserving its persistent state.',
				'reset' => 'Destroy the isolated test-site state and reprovision a fresh local Docker WordPress test site.',
				'status' => 'Report whether the isolated local Docker WordPress test site is reachable and ready for Shield browser testing.',
				'wp' => 'Run a WP-CLI command against the isolated Shield test site after ensuring it is ready.',
			] ),
			[
				'test:browser' => static function () use ( $projectRoot, $testSiteManager ) :Command {
					return new TestBrowserCommand( $projectRoot, new BrowserTestLane( null, $testSiteManager ) );
				},
				'test:source' => static function () use ( $projectRoot, $environmentResolver ) :Command {
					return new TestSourceCommand(
						$projectRoot,
						new SourceRuntimeTestLane( null, $environmentResolver )
					);
				},
				'test:integration-local' => static function () use ( $projectRoot, $environmentResolver ) :Command {
					return new TestIntegrationLocalCommand(
						$projectRoot,
						new LocalIntegrationTestLane( null, $environmentResolver )
					);
				},
				'test:package-targeted' => static function () use (
					$projectRoot,
					$packagePathResolver,
					$environmentResolver
				) :Command {
					return new TestPackageTargetedCommand(
						$projectRoot,
						new PackageTargetedTestLane( null, $packagePathResolver, $environmentResolver )
					);
				},
				'test:package-full' => static function () use (
					$projectRoot,
					$packagePathResolver,
					$environmentResolver
				) :Command {
					return new TestPackageFullCommand(
						$projectRoot,
						new PackageFullTestLane( null, $packagePathResolver, $environmentResolver )
					);
				},
				'analyze:source' => static function () use ( $projectRoot ) :Command {
					return new AnalyzeSourceCommand( $projectRoot, new SourceStaticAnalysisLane() );
				},
				'analyze:tooling' => static function () use ( $projectRoot ) :Command {
					return new AnalyzeToolingCommand( $projectRoot, new ToolingAnalysisLane() );
				},
				'analyze:package' => static function () use (
					$projectRoot,
					$packagePathResolver,
					$environmentResolver
				) :Command {
					return new AnalyzePackageCommand(
						$projectRoot,
						new PackageStaticAnalysisLane( $packagePathResolver, $environmentResolver )
					);
				},
			]
		);
	}

	/**
	 * @param array{up:string,down:string,reset:string,status:string,wp:string} $descriptions
	 * @return array<string,callable():Command>
	 */
	private function buildSiteCommandFactories(
		string $projectRoot,
		string $commandPrefix,
		LocalSiteManager $siteManager,
		array $descriptions
	) :array {
		return [
			$commandPrefix.':up' => static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteUpCommand( $commandPrefix.':up', $descriptions['up'], $projectRoot, $siteManager );
			},
			$commandPrefix.':down' => static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteDownCommand( $commandPrefix.':down', $descriptions['down'], $projectRoot, $siteManager );
			},
			$commandPrefix.':reset' => static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteResetCommand( $commandPrefix.':reset', $descriptions['reset'], $projectRoot, $siteManager );
			},
			$commandPrefix.':status' => static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteStatusCommand( $commandPrefix.':status', $descriptions['status'], $projectRoot, $siteManager );
			},
			$commandPrefix.':wp' => static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteWpCommand( $commandPrefix.':wp', $descriptions['wp'], $projectRoot, $siteManager );
			},
		];
	}

	public function run() :int {
		return $this->application->run();
	}
}

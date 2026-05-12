<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzePackageCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeToolingCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\GitPreCommitCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteDownCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteFixtureCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteResetCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteStatusCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteUpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\SiteWpCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestBrowserCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestCrossSiteCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestIntegrationLocalCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageFullCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageTargetedCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackagePathResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageTargetedTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PreCommitChangedFileLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\ToolingAnalysisLane;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
		$siteProfiles = $this->buildSiteProfiles( $environmentResolver );
		$siteFactories = [];

		foreach ( $siteProfiles as $profile ) {
			$siteFactories = \array_merge(
				$siteFactories,
				$this->buildSiteCommandFactories(
					$projectRoot,
					$profile[ 'prefix' ],
					$profile[ 'manager' ],
					$this->buildSiteCommandDescriptions( $profile )
				)
			);
		}

		return \array_merge(
			$siteFactories,
			[
				'test:browser' => static function () use ( $projectRoot ) :Command {
					return new TestBrowserCommand( $projectRoot, new BrowserTestLane() );
				},
				'test:cross-site' => static function () use ( $projectRoot ) :Command {
					return new TestCrossSiteCommand( $projectRoot, new CrossSiteTestLane() );
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
				'git:pre-commit' => static function () use ( $projectRoot ) :Command {
					return new GitPreCommitCommand( $projectRoot, new PreCommitChangedFileLane() );
				},
			]
		);
	}

	/**
	 * @return array<string,array{
	 *   prefix:string,
	 *   manager:LocalSiteManager,
	 *   site_label:string,
	 *   shield_label:string,
	 *   usage:string,
	 *   reset_scope:string
	 * }>
	 */
	private function buildSiteProfiles( TestingEnvironmentResolver $environmentResolver ) :array {
		return [
			'dev:site' => [
				'prefix' => 'dev:site',
				'manager' => new LocalSiteManager( LocalSiteDefinitions::dev(), null, $environmentResolver ),
				'site_label' => 'local Docker WordPress dev site',
				'shield_label' => 'local Shield dev site',
				'usage' => 'Shield source development',
				'reset_scope' => 'persistent dev-site state',
			],
			'test:site' => [
				'prefix' => 'test:site',
				'manager' => new LocalSiteManager( LocalSiteDefinitions::testFromEnvironment(), null, $environmentResolver ),
				'site_label' => 'isolated local Docker WordPress test site',
				'shield_label' => 'isolated Shield test site',
				'usage' => 'Shield browser testing',
				'reset_scope' => 'isolated test-site state',
			],
		];
	}

	/**
	 * @param array{
	 *   site_label:string,
	 *   shield_label:string,
	 *   usage:string,
	 *   reset_scope:string
	 * } $profile
	 * @return array{up:string,down:string,reset:string,status:string,wp:string,fixture?:string}
	 */
	private function buildSiteCommandDescriptions( array $profile ) :array {
		$descriptions = [
			'up' => sprintf( 'Start or reuse the %s for %s.', $profile[ 'site_label' ], $profile[ 'usage' ] ),
			'down' => sprintf( 'Stop the %s while preserving its persistent state.', $profile[ 'site_label' ] ),
			'reset' => sprintf(
				'Destroy the %s and reprovision a fresh %s.',
				$profile[ 'reset_scope' ],
				$profile[ 'site_label' ]
			),
			'status' => sprintf(
				'Report whether the %s is reachable and ready for %s.',
				$profile[ 'site_label' ],
				$profile[ 'usage' ]
			),
			'wp' => sprintf(
				'Run a WP-CLI command against the %s after ensuring it is ready.',
				$profile[ 'shield_label' ]
			),
		];

		if ( $profile[ 'prefix' ] === 'test:site' ) {
			$descriptions[ 'fixture' ] = sprintf(
				'Run a registered runtime fixture against the %s and return its JSON contract.',
				$profile[ 'shield_label' ]
			);
		}

		return $descriptions;
	}

	/**
	 * @param array{up:string,down:string,reset:string,status:string,wp:string,fixture?:string} $descriptions
	 * @return array<string,callable():Command>
	 */
	private function buildSiteCommandFactories(
		string $projectRoot,
		string $commandPrefix,
		LocalSiteManager $siteManager,
		array $descriptions
	) :array {
		$factories = [
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

		if ( isset( $descriptions[ 'fixture' ] ) ) {
			$factories[ $commandPrefix.':fixture' ] = static function () use ( $commandPrefix, $descriptions, $projectRoot, $siteManager ) :Command {
				return new SiteFixtureCommand( $commandPrefix.':fixture', $descriptions['fixture'], $projectRoot, $siteManager );
			};
		}

		return $factories;
	}

	public function run( ?InputInterface $input = null, ?OutputInterface $output = null ) :int {
		return $this->application->run( $input, $output );
	}
}

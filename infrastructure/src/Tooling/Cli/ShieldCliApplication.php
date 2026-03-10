<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzePackageCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeToolingCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestIntegrationLocalCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageFullCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageTargetedCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
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

		return [
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
		];
	}

	public function run() :int {
		return $this->application->run();
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli;

use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzePackageCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\AnalyzeSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageFullCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestPackageTargetedCommand;
use FernleafSystems\ShieldPlatform\Tooling\Cli\Command\TestSourceCommand;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackagePathResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageTargetedTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use Symfony\Component\Console\Application;

class ShieldCliApplication {

	private Application $application;

	public function __construct( string $projectRoot ) {
		$this->application = new Application( 'Shield Tooling CLI' );
		$this->application->setAutoExit( false );

		$environmentResolver = new TestingEnvironmentResolver();
		$packagePathResolver = new PackagePathResolver();

		$this->application->add(
			new TestSourceCommand( $projectRoot, new SourceRuntimeTestLane( null, $environmentResolver ) )
		);
		$this->application->add(
			new TestPackageTargetedCommand(
				$projectRoot,
				new PackageTargetedTestLane( null, $packagePathResolver, $environmentResolver )
			)
		);
		$this->application->add(
			new TestPackageFullCommand(
				$projectRoot,
				new PackageFullTestLane( null, $packagePathResolver, $environmentResolver )
			)
		);
		$this->application->add(
			new AnalyzeSourceCommand( $projectRoot, new SourceStaticAnalysisLane() )
		);
		$this->application->add(
			new AnalyzePackageCommand(
				$projectRoot,
				new PackageStaticAnalysisLane( $packagePathResolver, $environmentResolver )
			)
		);
	}

	public function run() :int {
		return $this->application->run();
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestPackageFullCommand extends Command {

	protected static $defaultName = 'test:package-full';

	private string $projectRoot;

	private PackageFullTestLane $lane;

	public function __construct( string $projectRoot, PackageFullTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run full packaged Docker runtime checks.' )
			->addOption(
				'package-path',
				null,
				InputOption::VALUE_REQUIRED,
				'Path to an already built plugin package. If omitted, a deterministic temp package is built.'
			)
			->addOption(
				'show-docker-output',
				null,
				InputOption::VALUE_NONE,
				'Show full Docker output instead of test-oriented compose output.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$packagePath = $input->getOption( 'package-path' );
			return $this->lane->run(
				$this->projectRoot,
				\is_string( $packagePath ) ? $packagePath : null,
				(bool)$input->getOption( 'show-docker-output' )
			);
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

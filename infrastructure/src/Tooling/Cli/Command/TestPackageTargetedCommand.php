<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageTargetedTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestPackageTargetedCommand extends Command {

	protected static $defaultName = 'test:package-targeted';

	private string $projectRoot;

	private PackageTargetedTestLane $lane;

	public function __construct( string $projectRoot, PackageTargetedTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run focused package validation checks.' )
			->addOption(
				'package-path',
				null,
				InputOption::VALUE_REQUIRED,
				'Path to an already built plugin package. If omitted, a deterministic temp package is built.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$packagePath = $input->getOption( 'package-path' );
			return $this->lane->run(
				$this->projectRoot,
				\is_string( $packagePath ) ? $packagePath : null
			);
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

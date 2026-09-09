<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCrossSiteCommand extends Command {

	protected static $defaultName = 'test:cross-site';

	private string $projectRoot;

	private CrossSiteTestLane $lane;

	public function __construct( string $projectRoot, CrossSiteTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run the two-site Docker WordPress import/export sync integration lane.' )
			->addOption(
				'show-setup-output',
				null,
				InputOption::VALUE_NONE,
				'Show Docker and setup command output during cross-site preparation.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->lane->run( $this->projectRoot, [
				'show_setup_output' => (bool)$input->getOption( 'show-setup-output' ),
			] );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

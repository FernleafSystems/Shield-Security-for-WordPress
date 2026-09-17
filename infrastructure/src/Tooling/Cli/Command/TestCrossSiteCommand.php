<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCrossSiteCommand extends Command {

	public const NAME = 'test:cross-site';

	private string $projectRoot;

	private CrossSiteTestLane $lane;

	public function __construct( string $projectRoot, CrossSiteTestLane $lane ) {
		parent::__construct( self::NAME );
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
			)
			->addOption(
				'b2-case',
				null,
				InputOption::VALUE_REQUIRED,
				'Run a supported named B2 remote evidence case (B2-01, B2-02, B2-07, or B2-09).'
			)
			->addOption(
				'e-case',
				null,
				InputOption::VALUE_REQUIRED,
				'Run a supported Group E export-success persistence case (E-01 or E-02).'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->lane->run( $this->projectRoot, [
				'show_setup_output' => (bool)$input->getOption( 'show-setup-output' ),
				'b2_case' => $input->getOption( 'b2-case' ),
				'e_case' => $input->getOption( 'e-case' ),
			] );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

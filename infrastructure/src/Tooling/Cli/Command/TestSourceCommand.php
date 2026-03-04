<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestSourceCommand extends Command {

	protected static $defaultName = 'test:source';

	private string $projectRoot;

	private SourceRuntimeTestLane $lane;

	public function __construct( string $projectRoot, SourceRuntimeTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run source runtime Docker checks against the working tree.' )
			->addOption(
				'refresh-setup',
				null,
				InputOption::VALUE_NONE,
				'Force refresh source setup cache and rerun setup steps.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->lane->run( $this->projectRoot, (bool)$input->getOption( 'refresh-setup' ) );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

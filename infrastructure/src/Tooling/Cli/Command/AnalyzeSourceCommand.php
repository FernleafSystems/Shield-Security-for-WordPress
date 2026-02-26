<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeSourceCommand extends Command {

	protected static $defaultName = 'analyze:source';

	private string $projectRoot;

	private SourceStaticAnalysisLane $lane;

	public function __construct( string $projectRoot, SourceStaticAnalysisLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this->setDescription( 'Run source static analysis checks.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->lane->run( $this->projectRoot );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

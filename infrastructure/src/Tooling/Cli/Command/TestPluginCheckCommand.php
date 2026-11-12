<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PluginCheckTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestPluginCheckCommand extends Command {

	protected static $defaultName = 'test:plugin-check';

	private string $projectRoot;

	private PluginCheckTestLane $lane;

	public function __construct( string $projectRoot, PluginCheckTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run WordPress.org Plugin Check against the packaged plugin and compare with the baseline.' )
			->addOption(
				'package-path',
				null,
				InputOption::VALUE_REQUIRED,
				'Path to an already built plugin package. If omitted, a deterministic temp package is built.'
			)
			->addOption(
				'mode',
				null,
				InputOption::VALUE_REQUIRED,
				'Plugin Check review mode: "new" (new submission) or "update".',
				'new'
			)
			->addOption(
				'update-baseline',
				null,
				InputOption::VALUE_NONE,
				'Accept every current finding by rewriting tests/plugin-check/baseline.json.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$packagePath = $input->getOption( 'package-path' );
			return $this->lane->run(
				$this->projectRoot,
				\is_string( $packagePath ) ? $packagePath : null,
				(string)$input->getOption( 'mode' ),
				(bool)$input->getOption( 'update-baseline' )
			);
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return PluginCheckTestLane::EXIT_SETUP_FAILURE;
		}
	}
}

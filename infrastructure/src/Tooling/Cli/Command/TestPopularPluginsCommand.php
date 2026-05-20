<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PopularPluginsCompatibilityTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestPopularPluginsCommand extends Command {

	protected static $defaultName = 'test:popular-plugins';

	private string $projectRoot;

	private PopularPluginsCompatibilityTestLane $lane;

	public function __construct( string $projectRoot, PopularPluginsCompatibilityTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run the packaged Shield popular plugin compatibility smoke lane.' )
			->addOption(
				'package-zip',
				null,
				InputOption::VALUE_REQUIRED,
				'Path to a built Shield release zip. If omitted, one is built with existing package tooling.'
			)
			->addOption(
				'artifact-dir',
				null,
				InputOption::VALUE_REQUIRED,
				'Directory for popular plugin compatibility summary, logs, and JSON artifacts.'
			)
			->addOption(
				'show-docker-output',
				null,
				InputOption::VALUE_NONE,
				'Mirror Docker and WP-CLI output to the console while still writing artifacts.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$packageZip = $input->getOption( 'package-zip' );
			$artifactDir = $input->getOption( 'artifact-dir' );

			return $this->lane->run(
				$this->projectRoot,
				\is_string( $packageZip ) && \trim( $packageZip ) !== '' ? $packageZip : null,
				\is_string( $artifactDir ) && \trim( $artifactDir ) !== '' ? $artifactDir : null,
				(bool)$input->getOption( 'show-docker-output' )
			);
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return PopularPluginsCompatibilityTestLane::EXIT_SETUP_FAILURE;
		}
	}
}

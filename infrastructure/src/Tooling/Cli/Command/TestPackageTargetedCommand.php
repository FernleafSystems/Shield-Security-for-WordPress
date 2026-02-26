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
			)
			->addOption(
				'fail-on-skipped',
				null,
				InputOption::VALUE_NONE,
				'Fail when tests are skipped.'
			)
			->addOption(
				'no-fail-on-skipped',
				null,
				InputOption::VALUE_NONE,
				'Do not fail when tests are skipped.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$packagePath = $input->getOption( 'package-path' );
			$failOnSkipped = $this->resolveFailOnSkippedOption( $input );

			return $this->lane->run(
				$this->projectRoot,
				\is_string( $packagePath ) ? $packagePath : null,
				$failOnSkipped
			);
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}

	private function resolveFailOnSkippedOption( InputInterface $input ) :?bool {
		$failOnSkipped = (bool)$input->getOption( 'fail-on-skipped' );
		$noFailOnSkipped = (bool)$input->getOption( 'no-fail-on-skipped' );

		if ( $failOnSkipped && $noFailOnSkipped ) {
			throw new \RuntimeException(
				'Options --fail-on-skipped and --no-fail-on-skipped cannot be used together.'
			);
		}
		if ( $failOnSkipped ) {
			return true;
		}
		if ( $noFailOnSkipped ) {
			return false;
		}

		return null;
	}
}

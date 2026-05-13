<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestBrowserCommand extends Command {

	protected static $defaultName = 'test:browser';

	private string $projectRoot;

	private BrowserTestLane $lane;

	public function __construct( string $projectRoot, BrowserTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run Playwright against the isolated local Docker WordPress test site for Shield source development.' )
			->addArgument(
				'playwright_args',
				InputArgument::IS_ARRAY,
				'Additional Playwright args (direct: -- --headed; composer: -- -- --headed).'
			)
			->addOption(
				'clean',
				null,
				InputOption::VALUE_NONE,
				'Force a clean browser lane reset before Playwright runs.'
			)
			->addOption(
				'warm',
				null,
				InputOption::VALUE_NONE,
				'Reuse warm browser lanes and refresh runtime incrementally before Playwright runs.'
			)
			->addOption(
				'show-setup-output',
				null,
				InputOption::VALUE_NONE,
				'Show Docker and setup command output during browser lane preparation.'
			)
			->addOption(
				'lanes',
				null,
				InputOption::VALUE_REQUIRED,
				'Number of browser lanes available to this run.'
			)
			->addOption(
				'runtime-refresh',
				null,
				InputOption::VALUE_REQUIRED,
				'Browser runtime host manifest mode: full (default) or auto.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$playwrightArgs = \array_values( \array_filter(
				(array)$input->getArgument( 'playwright_args' ),
				static function ( $value ) :bool {
					return \is_string( $value );
				}
			) );

			$mode = null;
			if ( $input->getOption( 'clean' ) && $input->getOption( 'warm' ) ) {
				throw new \InvalidArgumentException( 'Use only one of --clean or --warm.' );
			}
			if ( $input->getOption( 'clean' ) ) {
				$mode = 'clean';
			}
			elseif ( $input->getOption( 'warm' ) ) {
				$mode = 'warm';
			}

			$lanes = $input->getOption( 'lanes' );
			$runtimeRefresh = $input->getOption( 'runtime-refresh' );
			return $this->lane->run( $this->projectRoot, $playwrightArgs, [
				'mode'              => $mode,
				'lanes'             => \is_string( $lanes ) && $lanes !== '' ? $lanes : null,
				'show_setup_output' => (bool)$input->getOption( 'show-setup-output' ),
				'runtime_refresh'   => \is_string( $runtimeRefresh ) && $runtimeRefresh !== '' ? $runtimeRefresh : null,
			] );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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

			return $this->lane->run( $this->projectRoot, $playwrightArgs );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestIntegrationLocalCommand extends Command {

	protected static $defaultName = 'test:integration-local';

	private string $projectRoot;

	private LocalIntegrationTestLane $lane;

	public function __construct( string $projectRoot, LocalIntegrationTestLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run host-PHP integration tests with a local Docker MySQL sidecar.' )
			->addOption(
				'db-down',
				null,
				InputOption::VALUE_NONE,
				'Tear down local integration DB sidecar and exit.'
			)
			->addArgument(
				'phpunit_args',
				InputArgument::IS_ARRAY,
				'Additional PHPUnit args (direct: -- --filter FooTest; composer: -- -- --filter FooTest).'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$dbDown = (bool)$input->getOption( 'db-down' );
			$phpunitArgs = \array_values( \array_filter(
				(array)$input->getArgument( 'phpunit_args' ),
				static function ( $value ) :bool {
					return \is_string( $value );
				}
			) );

			return $this->lane->run( $this->projectRoot, $dbDown, $phpunitArgs );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

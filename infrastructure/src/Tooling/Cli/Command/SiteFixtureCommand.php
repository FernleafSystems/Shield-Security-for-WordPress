<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteFixtureCommand extends Command {

	private const FIXTURE_SCRIPT = '/app/tests/browser/support/run-runtime-fixture.php';

	private string $descriptionText;

	private string $projectRoot;

	private LocalSiteManager $siteManager;

	public function __construct(
		string $name,
		string $descriptionText,
		string $projectRoot,
		LocalSiteManager $siteManager
	) {
		$this->descriptionText = $descriptionText;
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
		parent::__construct( $name );
	}

	protected function configure() :void {
		$commandName = (string)$this->getName();

		$this
			->setDescription( $this->descriptionText )
			->addArgument( 'fixture', InputArgument::REQUIRED, 'Registered fixture key, for example "actions-queue".' )
			->addArgument( 'fixture_action', InputArgument::REQUIRED, 'Fixture action, for example "seed", "inspect", or "cleanup".' )
			->addArgument(
				'fixture_args',
				InputArgument::IS_ARRAY,
				\sprintf(
					'Optional fixture args (direct: %1$s actions-queue seed direct_table; composer: -- %1$s actions-queue seed direct_table).',
					$commandName
				)
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$fixture = $this->filterStringArgument( $input->getArgument( 'fixture' ) );
			$fixtureAction = $this->filterStringArgument( $input->getArgument( 'fixture_action' ) );
			$fixtureArgs = \array_values( \array_filter(
				(array)$input->getArgument( 'fixture_args' ),
				fn( $value ) :bool => \is_string( $value ) && $value !== ''
			) );

			$captured = $this->siteManager->wpCapture( $this->projectRoot, [
				'eval-file',
				self::FIXTURE_SCRIPT,
				'--',
				$fixture,
				$fixtureAction,
				...$fixtureArgs,
			] );

			$output->write( $this->extractJsonPayload( $captured[ 'stdout' ] ).\PHP_EOL );
			return Command::SUCCESS;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}

	/**
	 * @param mixed $value
	 */
	private function filterStringArgument( $value ) :string {
		return \is_string( $value ) ? \trim( $value ) : '';
	}

	private function extractJsonPayload( string $stdout ) :string {
		$lines = \array_reverse( \preg_split( '/\r?\n/', \trim( $stdout ) ) ?: [] );
		foreach ( $lines as $line ) {
			$line = \trim( $line );
			if ( $line === '' ) {
				continue;
			}
			$decoded = \json_decode( $line, true );
			if ( \json_last_error() === \JSON_ERROR_NONE ) {
				return $line;
			}
		}

		throw new \RuntimeException( 'Fixture command did not return a JSON payload.' );
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteFixtureCommand extends AbstractSiteCommand {

	private const FIXTURE_SCRIPT = '/app/tests/browser/support/run-runtime-fixture.php';

	protected function configure() :void {
		parent::configure();
		$commandName = (string)$this->getName();

		$this
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

	protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int {
		$fixture = $this->filterStringArgument( $input->getArgument( 'fixture' ) );
		$fixtureAction = $this->filterStringArgument( $input->getArgument( 'fixture_action' ) );
		$fixtureArgs = $this->stringArguments( $input->getArgument( 'fixture_args' ) );

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

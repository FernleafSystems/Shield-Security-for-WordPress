<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteWpCommand extends AbstractSiteCommand {

	protected function configure() :void {
		parent::configure();
		$commandName = (string)$this->getName();

		$this
			->addArgument(
				'wp_cli_args',
				InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				sprintf(
					'WP-CLI args to pass through (direct: %1$s plugin list; composer: -- %1$s plugin list).',
					$commandName
				)
			);
	}

	protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int {
		$wpCliArgs = $this->stringArguments( $input->getArgument( 'wp_cli_args' ) );

		return $this->siteManager->wp( $this->projectRoot, $wpCliArgs );
	}
}

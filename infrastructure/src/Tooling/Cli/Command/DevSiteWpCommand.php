<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevSiteWpCommand extends Command {

	protected static $defaultName = 'dev:site:wp';

	private string $projectRoot;

	private LocalDevSiteManager $siteManager;

	public function __construct( string $projectRoot, LocalDevSiteManager $siteManager ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run a WP-CLI command against the local Shield dev site after ensuring it is ready.' )
			->addArgument(
				'wp_cli_args',
				InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				'WP-CLI args to pass through (direct: dev:site:wp plugin list; composer: -- dev:site:wp plugin list).'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$wpCliArgs = \array_values( \array_filter(
				(array)$input->getArgument( 'wp_cli_args' ),
				static function ( $value ) :bool {
					return \is_string( $value ) && $value !== '';
				}
			) );

			return $this->siteManager->wp( $this->projectRoot, $wpCliArgs );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

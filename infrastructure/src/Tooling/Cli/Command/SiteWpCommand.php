<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteWpCommand extends Command {

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
			->addArgument(
				'wp_cli_args',
				InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				sprintf(
					'WP-CLI args to pass through (direct: %1$s plugin list; composer: -- %1$s plugin list).',
					$commandName
				)
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

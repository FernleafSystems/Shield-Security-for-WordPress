<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteStatusCommand extends Command {

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
		$this->setDescription( $this->descriptionText );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$status = $this->siteManager->status( $this->projectRoot );
			$output->writeln( sprintf( 'Site URL: %s', $status['site_url'] ) );
			$output->writeln( sprintf( 'Site healthy: %s', $status['site_healthy'] ? 'yes' : 'no' ) );
			$output->writeln( sprintf( 'Port open: %s', $status['port_open'] ? 'yes' : 'no' ) );
			$output->writeln( sprintf( 'Admin user: %s', $status['admin_user'] ) );
			return Command::SUCCESS;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevSiteStatusCommand extends Command {

	protected static $defaultName = 'dev:site:status';

	private string $projectRoot;

	private LocalDevSiteManager $siteManager;

	public function __construct( string $projectRoot, LocalDevSiteManager $siteManager ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
	}

	protected function configure() :void {
		$this->setDescription( 'Report whether the local Docker WordPress site is reachable and ready for Shield development.' );
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

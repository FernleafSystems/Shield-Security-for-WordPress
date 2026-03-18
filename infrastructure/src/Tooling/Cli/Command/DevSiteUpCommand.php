<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevSiteUpCommand extends Command {

	protected static $defaultName = 'dev:site:up';

	private string $projectRoot;

	private LocalDevSiteManager $siteManager;

	public function __construct( string $projectRoot, LocalDevSiteManager $siteManager ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
	}

	protected function configure() :void {
		$this->setDescription( 'Start or reuse the local Docker WordPress site for Shield source development.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$exitCode = $this->siteManager->up( $this->projectRoot );
			$output->writeln( sprintf( 'Local site ready at %s', LocalDevSiteManager::SITE_URL ) );
			$output->writeln( sprintf( 'Admin login: %s / %s', LocalDevSiteManager::ADMIN_USER, LocalDevSiteManager::ADMIN_PASSWORD ) );
			return $exitCode;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

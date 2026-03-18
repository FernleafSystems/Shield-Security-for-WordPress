<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevSiteResetCommand extends Command {

	protected static $defaultName = 'dev:site:reset';

	private string $projectRoot;

	private LocalDevSiteManager $siteManager;

	public function __construct( string $projectRoot, LocalDevSiteManager $siteManager ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
	}

	protected function configure() :void {
		$this->setDescription( 'Destroy the persistent local site state and reprovision a fresh local Docker WordPress site.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$exitCode = $this->siteManager->reset( $this->projectRoot );
			$output->writeln( sprintf( 'Local site reset and reprovisioned at %s', LocalDevSiteManager::SITE_URL ) );
			return $exitCode;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

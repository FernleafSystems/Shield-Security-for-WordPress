<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevSiteDownCommand extends Command {

	protected static $defaultName = 'dev:site:down';

	private string $projectRoot;

	private LocalDevSiteManager $siteManager;

	public function __construct( string $projectRoot, LocalDevSiteManager $siteManager ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
	}

	protected function configure() :void {
		$this->setDescription( 'Stop the local Docker WordPress site while preserving its persistent state.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->siteManager->down( $this->projectRoot );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

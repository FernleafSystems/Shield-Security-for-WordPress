<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteDownCommand extends Command {

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
			return $this->siteManager->down( $this->projectRoot );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}
}

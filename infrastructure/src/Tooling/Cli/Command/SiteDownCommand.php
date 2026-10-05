<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteDownCommand extends AbstractSiteCommand {

	protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int {
		return $this->siteManager->down( $this->projectRoot );
	}
}

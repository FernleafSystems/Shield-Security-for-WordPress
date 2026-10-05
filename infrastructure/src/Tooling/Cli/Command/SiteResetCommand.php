<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteResetCommand extends AbstractSiteCommand {

	protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int {
		$exitCode = $this->siteManager->reset( $this->projectRoot );
		$output->writeln( sprintf(
			'%s reset and reprovisioned at %s',
			$this->siteManager->definition()->label(),
			$this->siteManager->definition()->siteUrl()
		) );
		return $exitCode;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUpCommand extends AbstractSiteCommand {

	protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int {
		$exitCode = $this->siteManager->up( $this->projectRoot );
		$definition = $this->siteManager->definition();
		$output->writeln( sprintf( '%s ready at %s', $definition->label(), $definition->siteUrl() ) );
		$output->writeln( sprintf( 'Admin login: %s / %s', $definition->adminUser(), $definition->adminPassword() ) );
		return $exitCode;
	}
}

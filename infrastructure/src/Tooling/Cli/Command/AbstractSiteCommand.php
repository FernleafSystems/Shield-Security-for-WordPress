<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractSiteCommand extends Command {

	protected string $descriptionText;

	protected string $projectRoot;

	protected LocalSiteManager $siteManager;

	private BrowserTestLanePool $lanePool;

	public function __construct(
		string $name,
		string $descriptionText,
		string $projectRoot,
		LocalSiteManager $siteManager,
		?BrowserTestLanePool $lanePool = null
	) {
		$this->descriptionText = $descriptionText;
		$this->projectRoot = $projectRoot;
		$this->siteManager = $siteManager;
		$this->lanePool = $lanePool ?? new BrowserTestLanePool();
		parent::__construct( $name );
	}

	protected function configure() :void {
		$this->setDescription( $this->descriptionText );
	}

	/**
	 * @param mixed $value
	 * @return list<string>
	 */
	protected function stringArguments( $value ) :array {
		return \array_values( \array_filter( (array)$value, static fn( $argument ) :bool => \is_string( $argument ) && $argument !== '' ) );
	}

	final protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$action = function () use ( $input, $output ) :int {
				return $this->executeSiteCommand( $input, $output );
			};
			return $this->siteManager->definition()->usesSharedDatabase()
				? $this->lanePool->withSharedServiceAdmission( $this->projectRoot, $action, static function ( string $type, string $buffer ) use ( $output ) :void {
					$output->write( $buffer );
				} )
				: $action();
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}

	abstract protected function executeSiteCommand( InputInterface $input, OutputInterface $output ) :int;
}

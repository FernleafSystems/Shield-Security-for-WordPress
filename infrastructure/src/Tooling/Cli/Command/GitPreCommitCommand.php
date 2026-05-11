<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PreCommitChangedFileLane;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitPreCommitCommand extends Command {

	protected static $defaultName = 'git:pre-commit';

	private string $projectRoot;

	private PreCommitChangedFileLane $lane;

	public function __construct( string $projectRoot, PreCommitChangedFileLane $lane ) {
		parent::__construct();
		$this->projectRoot = $projectRoot;
		$this->lane = $lane;
	}

	protected function configure() :void {
		$this
			->setDescription( 'Run pre-commit checks for changed PHP files.' )
			->addOption( 'stdin', null, InputOption::VALUE_NONE, 'Read changed file paths from STDIN.' )
			->addOption( 'null', null, InputOption::VALUE_NONE, 'Parse STDIN as NUL-delimited path data.' )
			->addArgument( 'paths', InputArgument::IS_ARRAY, 'Changed file paths to check.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			return $this->lane->run( $this->projectRoot, $this->collectPaths( $input ) );
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}

	/**
	 * @return string[]
	 */
	private function collectPaths( InputInterface $input ) :array {
		$paths = \array_values( \array_filter(
			(array)$input->getArgument( 'paths' ),
			static fn( $path ) :bool => \is_string( $path ) && $path !== ''
		) );

		if ( (bool)$input->getOption( 'stdin' ) ) {
			$content = \file_get_contents( 'php://stdin' );
			if ( \is_string( $content ) && $content !== '' ) {
				$stdinPaths = (bool)$input->getOption( 'null' )
					? \explode( "\0", $content )
					: ( \preg_split( '/\r?\n/', $content ) ?: [] );
				$paths = \array_merge( $paths, \array_values( \array_filter(
					$stdinPaths,
					static fn( $path ) :bool => \is_string( $path ) && $path !== ''
				) ) );
			}
		}

		return $paths;
	}
}

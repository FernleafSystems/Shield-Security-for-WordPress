<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestBrowserCleanupCommand extends Command {

	public const NAME = 'test:browser:cleanup';

	private string $projectRoot;

	private DockerResourceSweeper $resourceSweeper;

	private LocalSiteRuntimeRefresher $runtimeRefresher;

	private BrowserTestLanePool $lanePool;

	public function __construct(
		string $projectRoot,
		?DockerResourceSweeper $resourceSweeper = null,
		?LocalSiteRuntimeRefresher $runtimeRefresher = null,
		?BrowserTestLanePool $lanePool = null
	) {
		parent::__construct( self::NAME );
		$this->projectRoot = $projectRoot;
		$this->resourceSweeper = $resourceSweeper ?? new DockerResourceSweeper();
		$this->runtimeRefresher = $runtimeRefresher ?? new LocalSiteRuntimeRefresher();
		$this->lanePool = $lanePool ?? new BrowserTestLanePool();
	}

	protected function configure() :void {
		$this
			->setDescription( 'Clean Shield browser-test Docker resources and runtime refresh staging files.' )
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Purge all browser harness Docker resources, including reusable warm volumes.'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Show planned browser harness cleanup without removing Docker resources or runtime workspaces.'
			)
			->addOption(
				'lanes',
				null,
				InputOption::VALUE_REQUIRED,
				'Number of browser lanes to include when purging compose projects.'
			)
			->addOption(
				'runtime-workspace-max-age-hours',
				null,
				InputOption::VALUE_REQUIRED,
				'Remove runtime refresh staging workspaces older than this many hours. --all removes them all.',
				'24'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$fullCleanup = (bool)$input->getOption( 'all' );
			$dryRun = (bool)$input->getOption( 'dry-run' );
			$laneCount = $this->resolveLaneCount( $input->getOption( 'lanes' ) );
			$workspaceMaxAgeSeconds = $fullCleanup
				? 0
				: $this->resolveWorkspaceMaxAgeSeconds( $input->getOption( 'runtime-workspace-max-age-hours' ) );

			$report = $this->resourceSweeper->cleanupRunResources(
				$this->projectRoot,
				'manual-cleanup',
				$laneCount,
				$fullCleanup,
				$dryRun
			);
			$workspaceActions = $this->runtimeRefresher->cleanupStaleWorkspaces( $this->projectRoot, $workspaceMaxAgeSeconds, $dryRun );

			if ( $dryRun ) {
				$this->writeDryRunPlan( $output, $report->plannedActions(), $workspaceActions );
			}

			if ( $report->findings() !== [] ) {
				$output->writeln( '<error>Browser cleanup left unexpected resources:</error>' );
				foreach ( $report->findings() as $finding ) {
					$output->writeln( ' - '.$finding );
				}
				return Command::FAILURE;
			}

			$output->writeln( $fullCleanup ? 'Browser harness purge complete.' : 'Browser harness cleanup complete.' );
			return Command::SUCCESS;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Error: '.$throwable->getMessage().'</error>' );
			return Command::FAILURE;
		}
	}

	/**
	 * @param mixed $value
	 */
	private function resolveLaneCount( $value ) :int {
		if ( \is_string( $value ) && $value !== '' ) {
			return $this->positiveInteger( $value, '--lanes' );
		}

		return $this->lanePool->laneCount();
	}

	/**
	 * @param mixed $value
	 */
	private function resolveWorkspaceMaxAgeSeconds( $value ) :int {
		if ( !\is_string( $value ) || $value === '' ) {
			return 24*60*60;
		}

		return $this->positiveInteger( $value, '--runtime-workspace-max-age-hours' )*60*60;
	}

	private function positiveInteger( string $value, string $source ) :int {
		if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
			throw new \InvalidArgumentException( $source.' must be a positive integer.' );
		}

		return (int)$value;
	}

	/**
	 * @param string[] $dockerActions
	 * @param string[] $workspaceActions
	 */
	private function writeDryRunPlan( OutputInterface $output, array $dockerActions, array $workspaceActions ) :void {
		$output->writeln( 'Browser cleanup dry-run plan:' );
		foreach ( $dockerActions as $action ) {
			$output->writeln( ' - Docker: '.$action );
		}
		foreach ( $workspaceActions as $action ) {
			$output->writeln( ' - Workspace: '.$action );
		}
		if ( $dockerActions === [] && $workspaceActions === [] ) {
			$output->writeln( ' - Nothing to remove.' );
		}
	}
}

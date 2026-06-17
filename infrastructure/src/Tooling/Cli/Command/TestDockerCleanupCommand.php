<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLanePool;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupPolicy;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestDockerCleanupCommand extends Command {

	public const NAME = 'test:docker:cleanup';

	private string $projectRoot;

	private ?ProcessRunner $processRunner;

	private BrowserTestLanePool $lanePool;

	public function __construct(
		string $projectRoot,
		?ProcessRunner $processRunner = null,
		?BrowserTestLanePool $lanePool = null
	) {
		parent::__construct( self::NAME );
		$this->projectRoot = $projectRoot;
		$this->processRunner = $processRunner;
		$this->lanePool = $lanePool ?? new BrowserTestLanePool();
	}

	protected function configure() :void {
		$this
			->setDescription( 'Clean labeled Shield source-test Docker resources by explicit harness scope.' )
			->addOption(
				'scope',
				null,
				InputOption::VALUE_REQUIRED,
				'Cleanup scope: '.\implode( ', ', DockerCleanupPolicy::scopes() ).'.'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Purge all resources in the selected scope, including reusable warm volumes.'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Show planned cleanup without removing Docker resources.'
			)
			->addOption(
				'lanes',
				null,
				InputOption::VALUE_REQUIRED,
				'Number of browser lanes to include when --scope=browser purges compose projects.'
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		try {
			$scope = $this->resolveScope( $input->getOption( 'scope' ) );
			$laneCount = $this->resolveLaneCount( $input->getOption( 'lanes' ), $scope );
			$fullCleanup = (bool)$input->getOption( 'all' );
			$dryRun = (bool)$input->getOption( 'dry-run' );
			$policy = DockerCleanupPolicy::forScope( $scope, $laneCount );
			$sweeper = new DockerResourceSweeper( $this->processRunner, $policy );

			$report = $sweeper->cleanupRunResources(
				$this->projectRoot,
				'manual-cleanup',
				$laneCount,
				$fullCleanup,
				$dryRun
			);

			if ( $dryRun ) {
				$this->writeDryRunPlan( $output, $scope, $report->plannedActions() );
			}

			if ( $report->findings() !== [] ) {
				$output->writeln( '<error>Docker cleanup left unexpected resources:</error>' );
				foreach ( $report->findings() as $finding ) {
					$output->writeln( ' - '.$finding );
				}
				return Command::FAILURE;
			}

			$output->writeln( $fullCleanup ? 'Docker harness purge complete for '.$scope.'.' : 'Docker harness cleanup complete for '.$scope.'.' );
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
	private function resolveScope( $value ) :string {
		if ( !\is_string( $value ) || $value === '' ) {
			throw new \InvalidArgumentException( '--scope is required.' );
		}

		if ( !\in_array( $value, DockerCleanupPolicy::scopes(), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported cleanup scope: '.$value );
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 */
	private function resolveLaneCount( $value, string $scope ) :int {
		if ( $scope !== DockerCleanupPolicy::SCOPE_BROWSER ) {
			return 1;
		}

		if ( \is_string( $value ) && $value !== '' ) {
			if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
				throw new \InvalidArgumentException( '--lanes must be a positive integer.' );
			}

			return (int)$value;
		}

		return $this->lanePool->laneCount();
	}

	/**
	 * @param string[] $actions
	 */
	private function writeDryRunPlan( OutputInterface $output, string $scope, array $actions ) :void {
		$output->writeln( 'Docker cleanup dry-run plan for '.$scope.':' );
		foreach ( $actions as $action ) {
			$output->writeln( ' - Docker: '.$action );
		}
		if ( $actions === [] ) {
			$output->writeln( ' - Nothing to remove.' );
		}
	}
}

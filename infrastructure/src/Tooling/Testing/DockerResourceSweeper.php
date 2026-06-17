<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class DockerResourceSweeper {

	private ProcessRunner $processRunner;

	private ?DockerCleanupPolicy $policy = null;

	public function __construct( ?ProcessRunner $processRunner = null, ?DockerCleanupPolicy $policy = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->policy = $policy;
	}

	private function policyForLaneCount( int $laneCount ) :DockerCleanupPolicy {
		if ( $this->policy === null || $this->policy->scope() === DockerCleanupPolicy::SCOPE_BROWSER ) {
			return DockerCleanupPolicy::browser( $laneCount );
		}

		return $this->policy;
	}

	public function startupSweep( string $rootDir ) :void {
		$report = new DockerCleanupReport();
		$policy = $this->policyForLaneCount( 1 );
		$this->removeResources( $rootDir, false, null, $report, $policy );
		if ( $policy->browserLegacyCleanup() ) {
			$this->removeLegacyBrowserResources( $rootDir, $report );
		}
		if ( $report->hasFindings() ) {
			throw new \RuntimeException( \implode( \PHP_EOL, $report->findings() ) );
		}
	}

	/**
	 * @return DockerCleanupReport Remaining unexpected resource descriptions.
	 */
	public function cleanupRunResources( string $rootDir, string $runId, int $laneCount, bool $fullCleanup, bool $dryRun = false ) :DockerCleanupReport {
		$report = new DockerCleanupReport( $dryRun );
		$policy = $this->policyForLaneCount( $laneCount );
		if ( $fullCleanup ) {
			$this->cleanupAllHarnessResources( $rootDir, $laneCount, $report );
			if ( !$dryRun ) {
				$this->auditNoHarnessResources( $rootDir, $report, $policy );
				if ( $policy->browserLegacyCleanup() ) {
					$this->auditLegacyBrowserResources( $rootDir, $report );
				}
			}
			return $report;
		}

		$this->removeResources( $rootDir, false, $runId, $report, $policy );
		if ( $policy->browserLegacyCleanup() ) {
			$this->removeLegacyBrowserResources( $rootDir, $report );
		}

		if ( !$dryRun ) {
			$this->auditWarmHarnessResources( $rootDir, $runId, $report, $policy );
			if ( $policy->browserLegacyCleanup() ) {
				$this->auditLegacyBrowserResources( $rootDir, $report );
			}
		}

		return $report;
	}

	public function cleanupAllHarnessResources( string $rootDir, int $laneCount, ?DockerCleanupReport $report = null ) :DockerCleanupReport {
		$report = $report ?? new DockerCleanupReport();
		$policy = $this->policyForLaneCount( $laneCount );
		$env = $policy->labelEnvironment(
			'cleanup',
			DockerHarnessLabels::LIFECYCLE_TRANSIENT,
			'shared',
			\gmdate( \DATE_ATOM ),
			'cleanup',
			DockerHarnessLabels::LIFECYCLE_TRANSIENT,
			\gmdate( \DATE_ATOM )
		);

		foreach ( $policy->composeDowns() as $composeDown ) {
			$command = [
				'docker',
				'compose',
				'-p',
				$composeDown[ 'project' ],
				'-f',
				$composeDown[ 'compose_file' ],
				'down',
				'-v',
				'--remove-orphans',
			];
			$this->runCleanupCommand( $command, $rootDir, $report, $composeDown[ 'description' ], $env, true );
		}

		$this->removeResources( $rootDir, true, null, $report, $policy );
		if ( $policy->browserLegacyCleanup() ) {
			$this->removeLegacyBrowserResources( $rootDir, $report );
		}

		return $report;
	}

	/**
	 * @return array<string,string>
	 */
	public function labelEnvironment(
		string $containerRunId,
		string $containerLifecycle,
		string $lane,
		string $containerExpiresAt,
		string $volumeRunId,
		string $volumeLifecycle,
		string $volumeExpiresAt
	) :array {
		return $this->policyForLaneCount( 1 )->labelEnvironment(
			$containerRunId,
			$containerLifecycle,
			$lane,
			$containerExpiresAt,
			$volumeRunId,
			$volumeLifecycle,
			$volumeExpiresAt
		);
	}

	private function removeResources(
		string $rootDir,
		bool $forceAll,
		?string $runId,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :void {
		$this->removeDockerObjects( $rootDir, 'container', 'rm', [ '-f' ], $forceAll, $runId, $report, $policy );
		$this->removeDockerObjects( $rootDir, 'volume', 'rm', [], $forceAll, $runId, $report, $policy );
		$this->removeDockerObjects( $rootDir, 'network', 'rm', [], $forceAll, $runId, $report, $policy );
	}

	/**
	 * @param string[] $removeFlags
	 */
	private function removeDockerObjects(
		string $rootDir,
		string $type,
		string $removeCommand,
		array $removeFlags,
		bool $forceAll,
		?string $runId,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :void {
		foreach ( $this->listLabeledResourceIds( $rootDir, $type, $report, $policy ) as $id ) {
			$labels = $this->inspectLabels( $rootDir, $type, $id, $report );
			$lifecycle = (string)( $labels[ DockerHarnessLabels::LIFECYCLE ] ?? '' );
			$resourceRunId = (string)( $labels[ DockerHarnessLabels::RUN_ID ] ?? '' );
			$expiresAt = (string)( $labels[ DockerHarnessLabels::EXPIRES_AT ] ?? '' );
			$expiryTs = $expiresAt === '' ? false : \strtotime( $expiresAt );
			$isExpired = $expiryTs !== false && $expiryTs <= \time();
			$isTransient = $lifecycle === DockerHarnessLabels::LIFECYCLE_TRANSIENT;
			$isCurrentRunTransient = $isTransient && $runId !== null && \hash_equals( $runId, $resourceRunId );
			$isMalformed = $lifecycle === '' || $resourceRunId === '' || $expiryTs === false;

			if ( !$forceAll && !( $isCurrentRunTransient || $isExpired || $isMalformed ) ) {
				continue;
			}

			$command = \array_merge(
				[ 'docker', $type, $removeCommand ],
				$removeFlags,
				[ $id ]
			);
			$this->runCleanupCommand( $command, $rootDir, $report, 'remove '.$type.' '.$id, null, true );
		}
	}

	/**
	 * @return string[]
	 */
	private function listLabeledResourceIds(
		string $rootDir,
		string $type,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :array {
		$command = [ 'docker', $type, 'ls' ];
		if ( $type === 'container' ) {
			$command[] = '-a';
		}
		$command = \array_merge( $command, [
			'-q',
			'--filter',
			'label='.DockerHarnessLabels::HARNESS.'='.$policy->harnessLabelValue(),
		] );
		$process = $this->runCleanupCommand( $command, $rootDir, $report, 'list '.$type.' resources' );
		if ( $process === null ) {
			return [];
		}

		return \array_values( \array_filter( \preg_split( '/\R+/', \trim( $process->getOutput() ) ) ?: [] ) );
	}

	private function auditNoHarnessResources( string $rootDir, DockerCleanupReport $report, DockerCleanupPolicy $policy ) :void {
		foreach ( [ 'container', 'volume', 'network' ] as $type ) {
			foreach ( $this->listLabeledResourceDetails( $rootDir, $type, $report, $policy ) as $resource ) {
				$report->addFinding( $this->describeResource( $type, $resource ).' remains after full cleanup.' );
			}
		}
	}

	private function auditWarmHarnessResources(
		string $rootDir,
		string $runId,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :void {
		foreach ( [ 'container', 'network' ] as $type ) {
			foreach ( $this->listLabeledResourceDetails( $rootDir, $type, $report, $policy ) as $resource ) {
				if ( $this->isActiveOtherRunTransient( $resource, $runId ) ) {
					continue;
				}
				$report->addFinding( $this->describeResource( $type, $resource ).' remains after warm cleanup.' );
			}
		}

		foreach ( $this->listLabeledResourceDetails( $rootDir, 'volume', $report, $policy ) as $resource ) {
			if ( $this->isValidReusableVolume( $resource ) || $this->isActiveOtherRunTransient( $resource, $runId ) ) {
				continue;
			}
			$report->addFinding( $this->describeResource( 'volume', $resource ).' is not a valid reusable warm volume.' );
		}
	}

	/**
	 * @return array<int,array{id:string,name:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false}>
	 */
	private function listLabeledResourceDetails(
		string $rootDir,
		string $type,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :array {
		$resources = [];
		foreach ( $this->listLabeledResourceIds( $rootDir, $type, $report, $policy ) as $id ) {
			$inspect = $this->inspectDockerResource( $rootDir, $type, $id, $report );
			$labels = $this->labelsFromInspectData( $inspect );
			$expiresAt = (string)( $labels[ DockerHarnessLabels::EXPIRES_AT ] ?? '' );
			$resources[] = [
				'id' => $id,
				'name' => $this->nameFromInspectData( $inspect, $id ),
				'lifecycle' => (string)( $labels[ DockerHarnessLabels::LIFECYCLE ] ?? '' ),
				'runId' => (string)( $labels[ DockerHarnessLabels::RUN_ID ] ?? '' ),
				'expiresAt' => $expiresAt,
				'expiryTs' => $expiresAt === '' ? false : \strtotime( $expiresAt ),
			];
		}

		return $resources;
	}

	/**
	 * @param array{id:string,name:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false} $resource
	 */
	private function isValidReusableVolume( array $resource ) :bool {
		return $resource[ 'lifecycle' ] === DockerHarnessLabels::LIFECYCLE_REUSABLE
			&& $resource[ 'runId' ] !== ''
			&& $resource[ 'expiryTs' ] !== false
			&& $resource[ 'expiryTs' ] > \time();
	}

	/**
	 * @param array{id:string,name:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false} $resource
	 */
	private function isActiveOtherRunTransient( array $resource, string $runId ) :bool {
		return $resource[ 'lifecycle' ] === DockerHarnessLabels::LIFECYCLE_TRANSIENT
			&& $resource[ 'runId' ] !== ''
			&& !\hash_equals( $runId, $resource[ 'runId' ] )
			&& $resource[ 'expiryTs' ] !== false
			&& $resource[ 'expiryTs' ] > \time();
	}

	/**
	 * @param array{id:string,name:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false} $resource
	 */
	private function describeResource( string $type, array $resource ) :string {
		return \sprintf(
			'%s %s (%s, lifecycle=%s, run-id=%s, expires-at=%s)',
			$type,
			$resource[ 'name' ],
			$resource[ 'id' ],
			$resource[ 'lifecycle' ] === '' ? 'missing' : $resource[ 'lifecycle' ],
			$resource[ 'runId' ] === '' ? 'missing' : $resource[ 'runId' ],
			$resource[ 'expiresAt' ] === '' ? 'missing' : $resource[ 'expiresAt' ]
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function inspectLabels( string $rootDir, string $type, string $id, DockerCleanupReport $report ) :array {
		return $this->labelsFromInspectData( $this->inspectDockerResource( $rootDir, $type, $id, $report ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function inspectDockerResource( string $rootDir, string $type, string $id, DockerCleanupReport $report ) :array {
		$process = $this->runCleanupCommand( [ 'docker', $type, 'inspect', $id ], $rootDir, $report, 'inspect '.$type.' '.$id );
		if ( $process === null ) {
			return [];
		}
		$data = \json_decode( $process->getOutput(), true );
		if ( !\is_array( $data ) || !isset( $data[ 0 ] ) || !\is_array( $data[ 0 ] ) ) {
			$report->addFinding( 'Docker cleanup command returned invalid inspect JSON: docker '.$type.' inspect '.$id );
			return [];
		}

		return $data[ 0 ];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,string>
	 */
	private function labelsFromInspectData( array $data ) :array {
		$labels = $data[ 'Config' ][ 'Labels' ] ?? $data[ 'Labels' ] ?? [];
		return \is_array( $labels ) ? $labels : [];
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function nameFromInspectData( array $data, string $fallback ) :string {
		$name = $data[ 'Name' ] ?? $fallback;
		return \is_string( $name ) && $name !== '' ? \ltrim( $name, '/' ) : $fallback;
	}

	private function removeLegacyBrowserResources( string $rootDir, DockerCleanupReport $report ) :void {
		foreach ( $this->legacyResourceIds( $rootDir, 'container', $report ) as $id ) {
			$this->runCleanupCommand( [ 'docker', 'container', 'rm', '-f', $id ], $rootDir, $report, 'remove legacy container '.$id, null, true );
		}
		foreach ( $this->legacyResourceIds( $rootDir, 'volume', $report ) as $id ) {
			$this->runCleanupCommand( [ 'docker', 'volume', 'rm', $id ], $rootDir, $report, 'remove legacy volume '.$id, null, true );
		}
		foreach ( $this->legacyResourceIds( $rootDir, 'network', $report ) as $id ) {
			$this->runCleanupCommand( [ 'docker', 'network', 'rm', $id ], $rootDir, $report, 'remove legacy network '.$id, null, true );
		}
	}

	private function auditLegacyBrowserResources( string $rootDir, DockerCleanupReport $report ) :void {
		foreach ( [ 'container', 'volume', 'network' ] as $type ) {
			foreach ( $this->legacyResourceIds( $rootDir, $type, $report ) as $id ) {
				$report->addFinding( 'legacy '.$type.' '.$id.' remains after cleanup.' );
			}
		}
	}

	/**
	 * @return string[]
	 */
	private function legacyResourceIds( string $rootDir, string $type, DockerCleanupReport $report ) :array {
		if ( $type === 'container' ) {
			return $this->legacyResourcesFromFormattedList(
				$rootDir,
				$type,
				[ 'docker', 'container', 'ls', '-a', '--format', '{{.ID}}\t{{.Names}}' ],
				$report
			);
		}
		if ( $type === 'volume' ) {
			return $this->legacyResourcesFromFormattedList(
				$rootDir,
				$type,
				[ 'docker', 'volume', 'ls', '--format', '{{.Name}}' ],
				$report
			);
		}
		if ( $type === 'network' ) {
			if ( $this->resourceHasHarnessLabel( $rootDir, 'network', LocalSiteDefinitions::BROWSER_NETWORK_NAME, $report, true ) ) {
				return [];
			}
			$process = $this->runOptionalInspect( [ 'docker', 'network', 'inspect', LocalSiteDefinitions::BROWSER_NETWORK_NAME ], $rootDir, $report, 'inspect legacy network '.LocalSiteDefinitions::BROWSER_NETWORK_NAME );
			return $process !== null && ( $process->getExitCode() ?? 1 ) === 0 ? [ LocalSiteDefinitions::BROWSER_NETWORK_NAME ] : [];
		}

		return [];
	}

	/**
	 * @param string[] $command
	 * @return string[]
	 */
	private function legacyResourcesFromFormattedList( string $rootDir, string $type, array $command, DockerCleanupReport $report ) :array {
		$process = $this->runCleanupCommand( $command, $rootDir, $report, 'list legacy '.$type.' resources' );
		if ( $process === null ) {
			return [];
		}

		$ids = [];
		foreach ( \preg_split( '/\R+/', \trim( $process->getOutput() ) ) ?: [] as $line ) {
			$line = \trim( $line );
			if ( $line === '' ) {
				continue;
			}
			$parts = \preg_split( '/\s+/', $line, 2 ) ?: [];
			$id = (string)( $parts[ 0 ] ?? '' );
			$name = $type === 'container' ? (string)( $parts[ 1 ] ?? '' ) : $id;
			if ( $id !== '' && $this->isLegacyBrowserResourceName( $type, $name )
				&& !$this->resourceHasHarnessLabel( $rootDir, $type, $id, $report )
			) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	private function isLegacyBrowserResourceName( string $type, string $name ) :bool {
		if ( $type === 'container' ) {
			return $name === LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME
				|| \preg_match( '/^shield-test-site-lane-\d+-/', $name ) === 1;
		}
		if ( $type === 'volume' ) {
			return $name === LocalSiteDefinitions::BROWSER_DB_VOLUME_NAME
				|| \preg_match( '/^shield-test-site-lane-\d+_site-(wp|plugin)$/', $name ) === 1;
		}

		return $name === LocalSiteDefinitions::BROWSER_NETWORK_NAME;
	}

	private function resourceHasHarnessLabel( string $rootDir, string $type, string $id, DockerCleanupReport $report, bool $optionalAbsence = false ) :bool {
		if ( $optionalAbsence ) {
			$command = [ 'docker', $type, 'inspect', $id ];
			$process = $this->runOptionalInspect( $command, $rootDir, $report, 'inspect '.$type.' '.$id );
			if ( $process === null || ( $process->getExitCode() ?? 1 ) !== 0 ) {
				return false;
			}
			$data = \json_decode( $process->getOutput(), true );
			if ( !\is_array( $data ) || !isset( $data[ 0 ] ) || !\is_array( $data[ 0 ] ) ) {
				$report->addFinding( 'Docker cleanup command returned invalid inspect JSON: '.\implode( ' ', $command ) );
				return false;
			}
			$labels = $this->labelsFromInspectData( $data[ 0 ] );
			return ( $labels[ DockerHarnessLabels::HARNESS ] ?? '' ) === LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE;
		}

		$labels = $this->inspectLabels( $rootDir, $type, $id, $report );
		return ( $labels[ DockerHarnessLabels::HARNESS ] ?? '' ) === LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE;
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runQuiet( array $command, string $rootDir, ?array $envOverrides = null ) :?Process {
		try {
			return $this->processRunner->run(
				$command,
				$rootDir,
				static function () :void {
				},
				$envOverrides
			);
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runCleanupCommand(
		array $command,
		string $rootDir,
		DockerCleanupReport $report,
		string $description,
		?array $envOverrides = null,
		bool $destructive = false
	) :?Process {
		if ( $destructive ) {
			$report->addPlannedAction( $description.': '.\implode( ' ', $command ) );
		}
		if ( $report->dryRun() && $destructive ) {
			return null;
		}

		$process = $this->runQuiet( $command, $rootDir, $envOverrides );
		if ( $process === null ) {
			$report->addFinding( 'Docker cleanup command failed to start: '.$description.' ('.\implode( ' ', $command ).')' );
			return null;
		}

		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			$stderr = \trim( $process->getErrorOutput() );
			$report->addFinding( \sprintf(
				'Docker cleanup command failed (%d): %s%s',
				$exitCode,
				\implode( ' ', $command ),
				$stderr === '' ? '' : ' STDERR: '.$stderr
			) );
			return null;
		}

		if ( $destructive ) {
			$report->addCompletedAction( $description );
		}
		return $process;
	}

	/**
	 * @param string[] $command
	 */
	private function runOptionalInspect(
		array $command,
		string $rootDir,
		DockerCleanupReport $report,
		string $description
	) :?Process {
		$process = $this->runQuiet( $command, $rootDir );
		if ( $process === null ) {
			$report->addFinding( 'Docker cleanup command failed to start: '.$description.' ('.\implode( ' ', $command ).')' );
			return null;
		}
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 && !$this->isMissingDockerResource( $process->getErrorOutput() ) ) {
			$report->addFinding( \sprintf(
				'Docker cleanup command failed (%d): %s STDERR: %s',
				$exitCode,
				\implode( ' ', $command ),
				\trim( $process->getErrorOutput() )
			) );
		}

		return $process;
	}

	private function isMissingDockerResource( string $stderr ) :bool {
		return \stripos( $stderr, 'No such' ) !== false
			|| \stripos( $stderr, 'not found' ) !== false;
	}
}

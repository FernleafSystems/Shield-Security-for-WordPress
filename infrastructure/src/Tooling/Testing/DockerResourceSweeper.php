<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type DockerResourceIdentity array{type:'container'|'volume'|'network',id:string}
 * @phpstan-type DockerResourceSnapshot array{daemonId:string,resources:list<DockerResourceIdentity>}
 * @phpstan-type DockerResourceMetadata array{harness:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false}
 * @phpstan-type DockerResourceDetails array{id:string,name:string,harness:string,lifecycle:string,runId:string,expiresAt:string,expiryTs:int|false}
 */
class DockerResourceSweeper {

	private ProcessRunner $processRunner;

	private ?DockerCleanupPolicy $policy = null;

	private bool $runOwnedCleanup = false;

	private ?string $daemonId = null;

	private ?string $boundRootDir = null;

	/** @var array<string,string|false>|null */
	private ?array $dockerEnvironment = null;

	public function __construct( ?ProcessRunner $processRunner = null, ?DockerCleanupPolicy $policy = null, bool $runOwnedCleanup = false ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->policy = $policy;
		$this->runOwnedCleanup = $runOwnedCleanup;
		if ( $runOwnedCleanup && $policy !== null && $policy->scope() !== DockerCleanupPolicy::SCOPE_BROWSER ) {
			throw new \InvalidArgumentException( 'Run-owned cleanup is supported only for browser resources.' );
		}
	}

	private function policyForLaneCount( int $laneCount ) :DockerCleanupPolicy {
		if ( $this->policy === null || $this->policy->scope() === DockerCleanupPolicy::SCOPE_BROWSER ) {
			return DockerCleanupPolicy::browser( $laneCount );
		}

		return $this->policy;
	}

	public function startupSweep( string $rootDir ) :void {
		if ( $this->runOwnedCleanup ) {
			$this->assertDockerDaemon( $rootDir );
			$this->assertFixedSlotsAvailable( $rootDir, null );
			return;
		}
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
		if ( $this->runOwnedCleanup ) {
			if ( $fullCleanup ) {
				$report->addFinding( 'Run-owned cleanup refuses full/global cleanup.' );
				return $report;
			}
			return $this->cleanupOwnedRunResources( $rootDir, $runId, [], $dryRun );
		}
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
		if ( $this->runOwnedCleanup ) {
			$report->addFinding( 'Run-owned cleanup refuses full/global cleanup.' );
			return $report;
		}
		$policy = $this->policyForLaneCount( $laneCount );
		$env = \array_merge( $policy->labelEnvironment(
			'cleanup',
			DockerHarnessLabels::LIFECYCLE_TRANSIENT,
			'shared',
			\gmdate( \DATE_ATOM ),
			'cleanup',
			DockerHarnessLabels::LIFECYCLE_TRANSIENT,
			\gmdate( \DATE_ATOM )
		), $policy->composeCleanupEnvironment() );

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
	 * @return array<string,string|false>
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
		if ( $this->runOwnedCleanup ) {
			$this->assertRunId( $containerRunId );
			if ( $this->boundRootDir === null || \preg_match( '/^lane-([1-9][0-9]*)$/D', $lane, $match ) !== 1 ) {
				throw new \RuntimeException( 'Run-owned allocation has no bound daemon or browser lane.' );
			}
			$this->assertDockerDaemon( $this->boundRootDir );
			$this->assertFixedSlotsAvailable( $this->boundRootDir, (int)$match[ 1 ], $containerRunId );
		}
		$environment = $this->policyForLaneCount( 1 )->labelEnvironment(
			$containerRunId,
			$containerLifecycle,
			$lane,
			$containerExpiresAt,
			$volumeRunId,
			$volumeLifecycle,
			$volumeExpiresAt
		);
		return $this->runOwnedCleanup ? \array_merge( $environment, $this->dockerEnvironment ?? [] ) : $environment;
	}

	/**
	 * Freeze the effective Docker endpoint, then bind allocation/recovery to its server identity.
	 * @return array{daemonId:string,environment:array<string,string|false>}
	 */
	public function bindDockerDaemon( string $rootDir, ?string $expectedDaemonId = null ) :array {
		$this->requireRunOwnedMode();
		if ( $expectedDaemonId !== null && !$this->validDaemonId( $expectedDaemonId ) ) {
			throw new \InvalidArgumentException( 'Recorded Docker daemon identity is invalid.' );
		}
		if ( $this->dockerEnvironment === null ) {
			$this->dockerEnvironment = [];
			foreach ( [ 'DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_CONFIG', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH', 'DOCKER_API_VERSION', 'DOCKER_SSH_COMMAND' ] as $key ) {
				$this->dockerEnvironment[ $key ] = \getenv( $key );
			}
			$context = $this->dockerEnvironment[ 'DOCKER_CONTEXT' ];
			$host = $this->dockerEnvironment[ 'DOCKER_HOST' ];
			if ( $context !== false && $context !== '' || $host === false || $host === '' ) {
				if ( $context === false || $context === '' ) {
					$context = \trim( $this->requiredQuietProcess( [ 'docker', 'context', 'show' ], $rootDir )->getOutput() );
				}
				if ( $context === '' || \preg_match( '/^[a-zA-Z0-9_.-]+$/D', $context ) !== 1 ) {
					throw new \RuntimeException( 'Docker context cannot be frozen safely.' );
				}
				$data = \json_decode( $this->requiredQuietProcess( [ 'docker', 'context', 'inspect', $context ], $rootDir )->getOutput(), true );
				$endpoint = $data[ 0 ][ 'Endpoints' ][ 'docker' ] ?? null;
				if ( !\is_array( $endpoint ) || !\is_string( $endpoint[ 'Host' ] ?? null ) || $endpoint[ 'Host' ] === '' ) {
					throw new \RuntimeException( 'Docker context transport is unavailable.' );
				}
				$this->dockerEnvironment[ 'DOCKER_HOST' ] = $endpoint[ 'Host' ];
				$this->dockerEnvironment[ 'DOCKER_CONTEXT' ] = false;
				$this->dockerEnvironment[ 'DOCKER_TLS' ] = false;
				$this->dockerEnvironment[ 'DOCKER_TLS_VERIFY' ] = false;
				$this->dockerEnvironment[ 'DOCKER_CERT_PATH' ] = false;
				$materials = $data[ 0 ][ 'TLSMaterial' ][ 'docker' ] ?? [];
				if ( $materials !== [] ) {
					$path = $data[ 0 ][ 'Storage' ][ 'TLSPath' ] ?? null;
					if ( !\is_string( $path ) || $path === '' || !\is_array( $materials ) ) {
						throw new \RuntimeException( 'Docker context TLS transport is unavailable.' );
					}
					$this->dockerEnvironment[ 'DOCKER_CERT_PATH' ] = \rtrim( $path, '/\\' ).'/docker';
					$this->dockerEnvironment[ 'DOCKER_TLS' ] = '1';
					$this->dockerEnvironment[ 'DOCKER_TLS_VERIFY' ] = ( $endpoint[ 'SkipTLSVerify' ] ?? false ) ? false : '1';
				}
			}
		}
		$observed = $this->observedDaemonId( $rootDir );
		if ( ( $expectedDaemonId !== null && !\hash_equals( $expectedDaemonId, $observed ) )
			|| ( $this->daemonId !== null && !\hash_equals( $this->daemonId, $observed ) ) ) {
			throw new \RuntimeException( 'Docker daemon identity changed; owned resources retained.' );
		}
		$this->daemonId = $observed;
		$this->boundRootDir = $rootDir;
		return [ 'daemonId' => $observed, 'environment' => $this->dockerEnvironment ];
	}

	/** @return DockerResourceSnapshot */
	public function snapshotRunResources( string $rootDir, string $runId ) :array {
		$this->assertDockerDaemon( $rootDir );
		$this->assertRunId( $runId );
		$report = new DockerCleanupReport();
		$resources = [];
		foreach ( [ 'container', 'volume', 'network' ] as $type ) {
			foreach ( $this->listLabeledResourceIds( $rootDir, $type, $report, $this->policyForLaneCount( 1 ) ) as $id ) {
				$data = $this->inspectDockerResource( $rootDir, $type, $id, $report );
				if ( $data !== null && $this->isOwnedTransient( $this->labelsFromInspectData( $data ), $runId ) ) {
					$canonical = $this->canonicalResourceId( $type, $data );
					if ( $canonical === null ) {
						$report->addFinding( 'Docker owned resource identity is invalid.' );
						continue;
					}
					$resources[] = [ 'type' => $type, 'id' => $canonical ];
				}
			}
		}
		$this->throwReportFindings( $report );
		$this->assertDockerDaemon( $rootDir );
		return [ 'daemonId' => (string)$this->daemonId, 'resources' => $resources ];
	}

	/** @param list<DockerResourceIdentity> $recordedResources */
	public function cleanupOwnedRunResources( string $rootDir, string $runId, array $recordedResources = [], bool $dryRun = false ) :DockerCleanupReport {
		$report = new DockerCleanupReport( $dryRun );
		try {
			$this->assertDockerDaemon( $rootDir );
			$this->assertRunId( $runId );
			if ( \array_values( $recordedResources ) !== $recordedResources ) {
				throw new \RuntimeException( 'Recorded Docker resources must be a list.' );
			}
			$resources = [];
			foreach ( $recordedResources as $resource ) {
				if ( !\is_array( $resource ) || \count( $resource ) !== 2 || !\is_string( $resource[ 'type' ] ?? null )
					|| !\is_string( $resource[ 'id' ] ?? null ) || !$this->validResourceId( $resource[ 'type' ], $resource[ 'id' ] ) ) {
					throw new \RuntimeException( 'Recorded Docker resource identity is invalid.' );
				}
				$resources[ $resource[ 'type' ].':'.$resource[ 'id' ] ] = $resource;
			}
			foreach ( $this->snapshotRunResources( $rootDir, $runId )[ 'resources' ] as $resource ) {
				$resources[ $resource[ 'type' ].':'.$resource[ 'id' ] ] = $resource;
			}
			\uasort( $resources, static fn( array $a, array $b ) :int => \array_search( $a[ 'type' ], [ 'container', 'volume', 'network' ], true ) <=> \array_search( $b[ 'type' ], [ 'container', 'volume', 'network' ], true ) );
			// Validate the complete receipt set before allowing any deletion.
			foreach ( $resources as $resource ) {
				$this->inspectOwnedResource( $rootDir, $resource, $runId, $report );
			}
			$this->throwReportFindings( $report );
			foreach ( $resources as $resource ) {
				$this->assertDockerDaemon( $rootDir );
				if ( !$this->inspectOwnedResource( $rootDir, $resource, $runId, $report ) ) {
					$this->throwReportFindings( $report );
					continue;
				}
				$command = [ 'docker', $resource[ 'type' ], 'rm' ];
				if ( $resource[ 'type' ] === 'container' ) {
					$command[] = '-f';
				}
				$command[] = $resource[ 'id' ];
				$this->runCleanupCommand( $command, $rootDir, $report, 'remove owned '.$resource[ 'type' ].' '.$resource[ 'id' ], null, true );
				$this->throwReportFindings( $report );
			}
			if ( !$dryRun && $this->snapshotRunResources( $rootDir, $runId )[ 'resources' ] !== [] ) {
				$report->addFinding( 'Owned Docker resources remain after cleanup.' );
			}
			$this->assertDockerDaemon( $rootDir );
		}
		catch ( \Throwable $error ) {
			$report->addFinding( $error->getMessage() );
		}
		return $report;
	}

	private function requireRunOwnedMode() :void {
		if ( !$this->runOwnedCleanup ) {
			throw new \RuntimeException( 'Run-owned Docker operations require explicit opt-in.' );
		}
	}

	private function assertRunId( string $runId ) :void {
		if ( \preg_match( '/^[a-zA-Z0-9_.-]{1,200}$/D', $runId ) !== 1 ) {
			throw new \InvalidArgumentException( 'Docker run identity is invalid.' );
		}
	}

	private function validDaemonId( string $id ) :bool {
		return \preg_match( '/^[a-zA-Z0-9:_.-]{1,200}$/D', $id ) === 1;
	}

	private function validResourceId( string $type, string $id ) :bool {
		return \in_array( $type, [ 'container', 'network' ], true )
			? \preg_match( '/^[a-f0-9]{64}$/D', $id ) === 1
			: $type === 'volume' && \preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,199}$/D', $id ) === 1;
	}

	private function observedDaemonId( string $rootDir ) :string {
		$id = \trim( $this->requiredQuietProcess( [ 'docker', 'info', '--format', '{{.ID}}' ], $rootDir )->getOutput() );
		if ( !$this->validDaemonId( $id ) ) {
			throw new \RuntimeException( 'Docker daemon identity is unavailable.' );
		}
		return $id;
	}

	private function assertDockerDaemon( string $rootDir ) :void {
		$this->requireRunOwnedMode();
		if ( $this->daemonId === null || $this->dockerEnvironment === null || !\hash_equals( $this->daemonId, $this->observedDaemonId( $rootDir ) ) ) {
			throw new \RuntimeException( 'Docker daemon is unbound or changed; owned resources retained.' );
		}
	}

	/** @param string[] $command */
	private function requiredQuietProcess( array $command, string $rootDir ) :Process {
		$process = $this->runQuiet( $command, $rootDir );
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException( $this->commandFailure( 'Docker ownership inspection', $command, $process ) );
		}
		return $process;
	}

	/** @param array<string,string> $labels */
	private function isOwnedTransient( array $labels, string $runId ) :bool {
		return ( $labels[ DockerHarnessLabels::HARNESS ] ?? null ) === LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE
			&& ( $labels[ DockerHarnessLabels::RUN_ID ] ?? null ) === $runId
			&& ( $labels[ DockerHarnessLabels::LIFECYCLE ] ?? null ) === DockerHarnessLabels::LIFECYCLE_TRANSIENT;
	}

	private function throwReportFindings( DockerCleanupReport $report ) :void {
		if ( $report->hasFindings() ) {
			throw new \RuntimeException( 'Docker ownership inspection or cleanup is incomplete; resources retained. '.\implode( ' ', $report->findings() ) );
		}
	}

	/** @param DockerResourceIdentity $resource */
	private function inspectOwnedResource( string $rootDir, array $resource, string $runId, DockerCleanupReport $report ) :bool {
		$data = $this->optionalResourceData( $rootDir, $resource[ 'type' ], $resource[ 'id' ], $report );
		if ( $data === null ) {
			return false;
		}
		$canonical = $this->canonicalResourceId( $resource[ 'type' ], $data );
		if ( $canonical !== $resource[ 'id' ] || !$this->isOwnedTransient( $this->labelsFromInspectData( $data ), $runId ) ) {
			$report->addFinding( 'Recorded Docker resource ownership changed; cleanup refused.' );
			return false;
		}
		return true;
	}

	/** @param array<string,mixed> $data */
	private function canonicalResourceId( string $type, array $data ) :?string {
		$id = $type === 'volume' ? ( $data[ 'Name' ] ?? null ) : ( $data[ 'Id' ] ?? null );
		return \is_string( $id ) && $this->validResourceId( $type, $id ) ? $id : null;
	}

	/** @return array<string,mixed>|null */
	private function optionalResourceData( string $rootDir, string $type, string $id, DockerCleanupReport $report ) :?array {
		$process = $this->runOptionalInspect( $type, $id, $rootDir, $report, 'inspect fixed/owned '.$type );
		return $process === null ? null : $this->decodeInspectData( $process->getOutput(), $report, 'Docker ownership inspection returned invalid JSON.', true );
	}

	private function assertFixedSlotsAvailable( string $rootDir, ?int $laneIndex, ?string $runId = null ) :void {
		$slots = [
			[ 'type' => 'container', 'id' => LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME ],
			[ 'type' => 'volume', 'id' => LocalSiteDefinitions::BROWSER_DB_VOLUME_NAME ],
			[ 'type' => 'network', 'id' => LocalSiteDefinitions::BROWSER_NETWORK_NAME ],
		];
		if ( $laneIndex !== null ) {
			$project = LocalSiteDefinitions::browserLane( $laneIndex )->composeProjectName();
			$slots[] = [ 'type' => 'volume', 'id' => $project.'_site-wp' ];
			$slots[] = [ 'type' => 'volume', 'id' => $project.'_site-plugin' ];
			// Include both Compose separators and one-off wp-cli names in this selected lane.
			$process = $this->requiredQuietProcess( [ 'docker', 'container', 'ls', '-a', '--format', '{{.ID}}\t{{.Names}}' ], $rootDir );
			foreach ( \preg_split( '/\R+/', \trim( $process->getOutput() ) ) ?: [] as $line ) {
				$parts = \preg_split( '/\s+/', \trim( $line ), 2 ) ?: [];
				if ( isset( $parts[ 1 ] ) && \preg_match( '/^'.\preg_quote( $project, '/' ).'[-_](wordpress|wp-cli)[-_]/', $parts[ 1 ] ) === 1 ) {
					$slots[] = [ 'type' => 'container', 'id' => $parts[ 0 ] ];
				}
			}
		}
		$report = new DockerCleanupReport();
		foreach ( $slots as $slot ) {
			$data = $this->optionalResourceData( $rootDir, $slot[ 'type' ], $slot[ 'id' ], $report );
			if ( $data === null ) {
				continue;
			}
			$labels = $this->labelsFromInspectData( $data );
			$metadata = $this->resourceMetadata( $labels );
			$expiry = $metadata[ 'expiryTs' ];
			$reusable = $slot[ 'type' ] === 'volume'
				&& $metadata[ 'harness' ] === LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE
				&& $this->isValidReusableVolume( $metadata );
			$ownSharedTransient = $runId !== null && $expiry !== false && $expiry > \time()
				&& \in_array( $slot[ 'id' ], [ LocalSiteDefinitions::BROWSER_DB_CONTAINER_NAME, LocalSiteDefinitions::BROWSER_NETWORK_NAME ], true )
				&& $this->isOwnedTransient( $labels, $runId );
			if ( !$reusable && !$ownSharedTransient ) {
				$report->addFinding( 'Docker fixed '.$slot[ 'type' ].' slot is occupied; allocation refused.' );
			}
		}
		$this->throwReportFindings( $report );
		$this->assertDockerDaemon( $rootDir );
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
			$data = $this->inspectDockerResource( $rootDir, $type, $id, $report );
			if ( $data === null ) {
				// Unreadable labels are not proof of a removable resource; the finding keeps it visible.
				continue;
			}
			$metadata = $this->resourceMetadata( $this->labelsFromInspectData( $data ) );
			$lifecycle = $metadata[ 'lifecycle' ];
			$resourceRunId = $metadata[ 'runId' ];
			$expiryTs = $metadata[ 'expiryTs' ];
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
	 * @return list<DockerResourceDetails>
	 */
	private function listLabeledResourceDetails(
		string $rootDir,
		string $type,
		DockerCleanupReport $report,
		DockerCleanupPolicy $policy
	) :array {
		$resources = [];
		foreach ( $this->listLabeledResourceIds( $rootDir, $type, $report, $policy ) as $id ) {
			$inspect = $this->inspectDockerResource( $rootDir, $type, $id, $report ) ?? [];
			$resources[] = \array_merge( $this->resourceMetadata( $this->labelsFromInspectData( $inspect ) ), [
				'id' => $id,
				'name' => $this->nameFromInspectData( $inspect, $id ),
			] );
		}

		return $resources;
	}

	/**
	 * @param DockerResourceMetadata $resource
	 */
	private function isValidReusableVolume( array $resource ) :bool {
		return $resource[ 'lifecycle' ] === DockerHarnessLabels::LIFECYCLE_REUSABLE
			&& $resource[ 'runId' ] !== ''
			&& $resource[ 'expiryTs' ] !== false
			&& $resource[ 'expiryTs' ] > \time();
	}

	/**
	 * @param DockerResourceMetadata $resource
	 */
	private function isActiveOtherRunTransient( array $resource, string $runId ) :bool {
		return $resource[ 'lifecycle' ] === DockerHarnessLabels::LIFECYCLE_TRANSIENT
			&& $resource[ 'runId' ] !== ''
			&& !\hash_equals( $runId, $resource[ 'runId' ] )
			&& $resource[ 'expiryTs' ] !== false
			&& $resource[ 'expiryTs' ] > \time();
	}

	/**
	 * @param DockerResourceDetails $resource
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
	 * @return array<string,mixed>|null Null when the inspection failed or was unreadable; the finding is recorded.
	 */
	private function inspectDockerResource( string $rootDir, string $type, string $id, DockerCleanupReport $report ) :?array {
		$process = $this->runCleanupCommand( [ 'docker', $type, 'inspect', $id ], $rootDir, $report, 'inspect '.$type.' '.$id );
		if ( $process === null ) {
			return null;
		}
		return $this->decodeInspectData( $process->getOutput(), $report, 'Docker cleanup command returned invalid inspect JSON: docker '.$type.' inspect '.$id );
	}

	/** @return array<string,mixed>|null */
	private function decodeInspectData( string $json, DockerCleanupReport $report, string $invalidFinding, bool $requireSingle = false ) :?array {
		$data = \json_decode( $json, true );
		if ( !\is_array( $data ) || !\is_array( $data[ 0 ] ?? null ) || ( $requireSingle && \count( $data ) !== 1 ) ) {
			$report->addFinding( $invalidFinding );
			return null;
		}
		return $data[ 0 ];
	}

	/**
	 * @param array<string,string> $labels
	 * @return DockerResourceMetadata
	 */
	private function resourceMetadata( array $labels ) :array {
		$expiresAt = $labels[ DockerHarnessLabels::EXPIRES_AT ] ?? '';
		return [
			'harness' => $labels[ DockerHarnessLabels::HARNESS ] ?? '',
			'lifecycle' => $labels[ DockerHarnessLabels::LIFECYCLE ] ?? '',
			'runId' => $labels[ DockerHarnessLabels::RUN_ID ] ?? '',
			'expiresAt' => $expiresAt,
			'expiryTs' => $expiresAt === '' ? false : \strtotime( $expiresAt ),
		];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,string>
	 */
	private function labelsFromInspectData( array $data ) :array {
		$labels = $data[ 'Config' ][ 'Labels' ] ?? $data[ 'Labels' ] ?? [];
		if ( !\is_array( $labels ) ) {
			return [];
		}
		$normalized = [];
		foreach ( $labels as $key => $value ) {
			if ( \is_string( $key ) && \is_string( $value ) ) {
				$normalized[ $key ] = $value;
			}
		}
		return $normalized;
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
			$name = LocalSiteDefinitions::BROWSER_NETWORK_NAME;
			$process = $this->runOptionalInspect( 'network', $name, $rootDir, $report, 'inspect legacy network '.$name );
			if ( $process === null ) {
				return [];
			}
			$data = $this->decodeInspectData( $process->getOutput(), $report, 'Docker cleanup command returned invalid inspect JSON: docker network inspect '.$name );
			return $this->isReadableUnlabelledResource( $data ) ? [ $name ] : [];
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
				&& $this->isReadableUnlabelledResource( $this->inspectDockerResource( $rootDir, $type, $id, $report ) )
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

	/**
	 * A legacy-named resource is removable only when its inspection was read and carries no harness label.
	 * @param array<string,mixed>|null $data
	 */
	private function isReadableUnlabelledResource( ?array $data ) :bool {
		return $data !== null
			&& ( $this->labelsFromInspectData( $data )[ DockerHarnessLabels::HARNESS ] ?? '' ) !== LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE;
	}

	/**
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runQuiet( array $command, string $rootDir, ?array $envOverrides = null ) :Process {
		if ( $this->runOwnedCleanup && $this->dockerEnvironment !== null ) {
			$envOverrides = \array_merge( $envOverrides ?? [], $this->dockerEnvironment );
		}
		return $this->processRunner->run(
			$command,
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);
	}

	/**
	 * Report boundary: a launch exception becomes a finding that keeps the original message.
	 * @param string[] $command
	 * @param array<string,string|false>|null $envOverrides
	 */
	private function runReported( array $command, string $rootDir, DockerCleanupReport $report, string $description, ?array $envOverrides = null ) :?Process {
		try {
			return $this->runQuiet( $command, $rootDir, $envOverrides );
		}
		catch ( \Throwable $error ) {
			$report->addFinding( 'Docker cleanup command failed to start: '.$description.' ('.\implode( ' ', $command ).'): '.$error->getMessage() );
			return null;
		}
	}

	/** @param string[] $command */
	private function commandFailure( string $subject, array $command, Process $process ) :string {
		$stderr = \trim( $process->getErrorOutput() );
		$stdout = \trim( $process->getOutput() );
		return \sprintf(
			'%s failed (%d): %s%s',
			$subject,
			$process->getExitCode() ?? 1,
			\implode( ' ', $command ),
			$stderr !== '' ? ' STDERR: '.$stderr : ( $stdout !== '' ? ' STDOUT: '.$stdout : '' )
		);
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
		if ( $destructive && $this->runOwnedCleanup ) {
			$this->assertDockerDaemon( $rootDir );
		}
		if ( $destructive ) {
			$report->addPlannedAction( $description.': '.\implode( ' ', $command ) );
		}
		if ( $report->dryRun() && $destructive ) {
			return null;
		}

		$process = $this->runReported( $command, $rootDir, $report, $description, $envOverrides );
		if ( $process === null ) {
			return null;
		}
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$report->addFinding( $this->commandFailure( 'Docker cleanup command', $command, $process ) );
			return null;
		}

		if ( $destructive ) {
			$report->addCompletedAction( $description );
		}
		return $process;
	}

	/**
	 * Inspect a resource that may be absent.
	 * @return Process|null The successful inspection; null when the resource is confirmed missing or the
	 *                      inspection failed, and every failure is recorded as a finding.
	 */
	private function runOptionalInspect( string $type, string $id, string $rootDir, DockerCleanupReport $report, string $description ) :?Process {
		$command = [ 'docker', $type, 'inspect', $id ];
		$process = $this->runReported( $command, $rootDir, $report, $description );
		if ( $process === null || ( $process->getExitCode() ?? 1 ) === 0 ) {
			return $process;
		}
		if ( !$this->isMissingDockerResource( $type, $id, $process->getErrorOutput() ) ) {
			$report->addFinding( $this->commandFailure( 'Docker cleanup command', $command, $process ) );
		}
		return null;
	}

	/**
	 * Only Docker's exact "no such object" response line for this resource proves absence.
	 */
	private function isMissingDockerResource( string $type, string $id, string $stderr ) :bool {
		$quotedId = \preg_quote( $id, '/' );
		$responses = 'No such (?:'.\preg_quote( $type, '/' ).'|object):\s*'.$quotedId
					 .'|get '.$quotedId.': no such volume'
					 .'|network '.$quotedId.' not found';
		return \preg_match( '/^(?:Error(?: response from daemon)?:\s*)?(?:'.$responses.')[ \t]*\r?$/im', $stderr ) === 1;
	}
}

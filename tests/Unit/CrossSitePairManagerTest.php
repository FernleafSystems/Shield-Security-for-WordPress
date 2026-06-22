<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSitePairManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class CrossSitePairManagerTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'SHIELD_CROSS_SITE_MASTER_PORT',
			'SHIELD_CROSS_SITE_SLAVE_PORT',
		] as $name ) {
			\putenv( $name );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testProvisionCommandUsesInternalMasterUrlAndExistingProvisionScript() :void {
		$command = $this->invokePrivate(
			new CrossSitePairManager(),
			'buildProvisionCommand',
			[ 'master' ]
		);

		$this->assertContains( '-f', $command );
		$this->assertContains( 'tests/docker/docker-compose.cross-site.yml', $command );
		$this->assertContains( 'SHIELD_LOCAL_SITE_URL=http://wordpress-master', $command );
		$this->assertContains( 'SHIELD_LOCAL_SITE_PROFILE=cross-site-master', $command );
		$this->assertContains( 'wp-cli-master', $command );
		$this->assertContains( '/app/tests/docker/provision-local-site.sh', $command );
	}

	public function testWpCliCommandTargetsSlaveServiceAndAppendsAllowRoot() :void {
		$command = $this->invokePrivate(
			new CrossSitePairManager(),
			'buildWpCliCommand',
			[ 'slave', [ 'plugin', 'list' ] ]
		);

		$this->assertSame( 'docker', $command[ 0 ] );
		$this->assertContains( 'tests/docker/docker-compose.cross-site.yml', $command );
		$this->assertContains( 'wp-cli-slave', $command );
		$this->assertContains( 'plugin', $command );
		$this->assertContains( 'list', $command );
		$this->assertSame( '--allow-root', $command[ \count( $command ) - 1 ] );
	}

	public function testComposeExecutionCommandIncludesCrossSiteProjectName() :void {
		$command = $this->invokePrivate(
			new CrossSitePairManager(),
			'buildComposeCommandForExecution',
			[ [ 'ps' ] ]
		);

		$this->assertSame( 'docker', $command[ 0 ] );
		$this->assertContains( '-p', $command );
		$this->assertContains( 'shield-cross-site', $command );
		$this->assertContains( 'tests/docker/docker-compose.cross-site.yml', $command );
	}

	public function testRuntimeEnvironmentUsesCrossSiteProjectAndDiagnosticPorts() :void {
		\putenv( 'SHIELD_CROSS_SITE_MASTER_PORT=8992' );
		\putenv( 'SHIELD_CROSS_SITE_SLAVE_PORT=8993' );
		$root = $this->createTrackedTempDir( 'shield-cross-site-manager-' );
		$manager = new CrossSitePairManager(
			null,
			new RecordingTestingEnvironmentResolver( '8.3' )
		);

		$env = $this->invokePrivate( $manager, 'buildRuntimeEnvOverrides', [ $root ] );

		$this->assertSame( 'shield-cross-site', $env[ 'COMPOSE_PROJECT_NAME' ] );
		$this->assertSame( '8.3', $env[ 'PHP_VERSION' ] );
		$this->assertSame( '8992', $env[ 'SHIELD_CROSS_SITE_MASTER_PORT' ] );
		$this->assertSame( '8993', $env[ 'SHIELD_CROSS_SITE_SLAVE_PORT' ] );
		$this->assertArrayHasKey( 'SHIELD_PACKAGE_PATH', $env );
		$this->assertFalse( $env[ 'SHIELD_PACKAGE_PATH' ] );
		$this->assertCrossSiteReusableLabelEnv( $env );
	}

	public function testRuntimeEnvironmentDockerLabelsRemainStableAcrossRepeatedBuilds() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-manager-stable-' );
		$manager = new CrossSitePairManager(
			null,
			new RecordingTestingEnvironmentResolver( '8.2' )
		);

		$first = $this->invokePrivate( $manager, 'buildRuntimeEnvOverrides', [ $root ] );
		$this->waitForUnixSecondToChange();
		$second = $this->invokePrivate( $manager, 'buildRuntimeEnvOverrides', [ $root ] );

		$this->assertSameDockerLabelEnvironment( $first, $second );
	}

	public function testRuntimeEnvironmentDockerLabelsRemainStableAcrossManagerInstances() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-manager-reusable-' );

		$first = $this->invokePrivate(
			new CrossSitePairManager(
				null,
				new RecordingTestingEnvironmentResolver( '8.2' )
			),
			'buildRuntimeEnvOverrides',
			[ $root ]
		);
		$second = $this->invokePrivate(
			new CrossSitePairManager(
				null,
				new RecordingTestingEnvironmentResolver( '8.2' )
			),
			'buildRuntimeEnvOverrides',
			[ $root ]
		);

		$this->assertSameDockerLabelEnvironment( $first, $second );
	}

	public function testDatabaseResetSqlDropsAndRecreatesBothDatabases() :void {
		$sql = $this->invokePrivate( new CrossSitePairManager(), 'buildResetDatabasesSql' );

		$this->assertStringContainsString( 'DROP DATABASE IF EXISTS `shield_cross_site_master`', $sql );
		$this->assertStringContainsString( 'CREATE DATABASE `shield_cross_site_master`', $sql );
		$this->assertStringContainsString( 'DROP DATABASE IF EXISTS `shield_cross_site_slave`', $sql );
		$this->assertStringContainsString( 'CREATE DATABASE `shield_cross_site_slave`', $sql );
	}

	public function testExportComparisonExclusionsIncludeLocalStateAndRuntimeInvariants() :void {
		$exclusions = $this->invokePrivate(
			new CrossSitePairManager(),
			'exportComparisonExclusions',
			[
				[
					'local_state_exceptions' => [ 'importexport_masterurl' ],
					'runtime_invariant_keys' => [ 'global_enable_plugin_features' ],
				],
				[
					'local_state_exceptions' => [ 'importexport_masterurl' ],
					'runtime_invariant_keys' => [ 'importexport_enable' ],
				],
			]
		);

		$this->assertSame(
			[
				'importexport_masterurl',
				'global_enable_plugin_features',
				'importexport_enable',
			],
			$exclusions
		);
	}

	public function testOptionsDiffDistinguishesMissingKeysFromNullValues() :void {
		$diff = $this->invokePrivate(
			new CrossSitePairManager(),
			'buildOptionsDiff',
			[
				[
					'present_null' => null,
				],
				[],
			]
		);

		$this->assertSame(
			[
				'present_null' => [
					'master' => null,
					'slave'  => [ '__missing__' => true ],
				],
			],
			$diff
		);
	}

	public function testHelperFailurePrefersStructuredJsonOverDockerNoise() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-helper-failure-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				[
					'exit_code' => 1,
					'stdout' => "{\"ok\":false,\"error\":{\"message\":\"Generated corpus options did not change from baseline after storage: sample_key\"}}\n",
					'stderr' => "Container shield-cross-site-db-1 Running \n",
				],
			] )
		);

		try {
			$this->invokePrivate( $manager, 'runHelper', [ $root, 'master', 'apply-corpus' ] );
			$this->fail( 'Expected structured helper failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Generated corpus options did not change from baseline after storage: sample_key',
				$exception->getMessage()
			);
			$this->assertStringNotContainsString( 'Container shield-cross-site-db-1', $exception->getMessage() );
		}
	}

	public function testHelperFailureReadsStructuredJsonAfterHarmlessStdoutNoise() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-helper-noisy-stdout-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				[
					'exit_code' => 1,
					'stdout' => "WP-CLI informational line\nAnother harmless line\n{\"ok\":false,\"error\":{\"message\":\"real structured helper error\"}}\n",
					'stderr' => "Container shield-cross-site-db-1 Running \n",
				],
			] )
		);

		try {
			$this->invokePrivate( $manager, 'runHelper', [ $root, 'master', 'apply-corpus' ] );
			$this->fail( 'Expected structured helper failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame( 'real structured helper error', $exception->getMessage() );
			$this->assertStringNotContainsString( 'WP-CLI informational line', $exception->getMessage() );
			$this->assertStringNotContainsString( 'Container shield-cross-site-db-1', $exception->getMessage() );
		}
	}

	public function testWpCliFailureDiagnosticsAreTrimmed() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-helper-long-failure-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				[
					'exit_code' => 1,
					'stdout' => '',
					'stderr' => \str_repeat( 'docker noise ', 200 ),
				],
			] )
		);

		try {
			$this->invokePrivate( $manager, 'runHelper', [ $root, 'master', 'apply-corpus' ] );
			$this->fail( 'Expected WP-CLI failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'WP-CLI command failed on master with exit code 1.', $exception->getMessage() );
			$this->assertStringEndsWith( '...', $exception->getMessage() );
			$this->assertLessThan( 1300, \strlen( $exception->getMessage() ) );
		}
	}

	public function testHelperDecodeFailureDiagnosticsAreTrimmed() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-helper-long-decode-failure-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				[
					'exit_code' => 0,
					'stdout' => \str_repeat( 'unexpected helper output ', 100 ),
					'stderr' => '',
				],
			] )
		);

		try {
			$this->invokePrivate( $manager, 'runHelper', [ $root, 'master', 'apply-corpus' ] );
			$this->fail( 'Expected helper decode failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'Cross-site helper did not return a JSON object.', $exception->getMessage() );
			$this->assertStringEndsWith( '...', $exception->getMessage() );
			$this->assertLessThan( 1300, \strlen( $exception->getMessage() ) );
		}
	}

	public function testSlaveImportWaitRunsScheduledImportCron() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-wait-scheduled-' );
		$runner = new RecordingProcessRunner( [
			$this->helperSuccessProcess( $this->waitingExportQueueState() ),
			$this->helperSuccessProcess( $this->slaveCronState( true, true ) ),
			[ 'exit_code' => 0 ],
			$this->helperSuccessProcess( $this->postExportQueueState() ),
		] );
		$manager = new CrossSitePairManager( $runner );

		$result = $this->invokePrivate( $manager, 'waitForSlaveImportCompletion', [ $root ] );

		$this->assertSame( 'idle', $result[ 'rows' ][ 0 ][ 'queue_status' ] );
		$cronCommand = $this->findProcessCommandContaining( $runner, 'cron event run shield-plugin-importexport-update-notified' );
		$this->assertContains( 'cron', $cronCommand );
		$this->assertContains( 'event', $cronCommand );
		$this->assertContains( 'run', $cronCommand );
		$this->assertContains( 'shield-plugin-importexport-update-notified', $cronCommand );
	}

	public function testSlaveImportWaitRunsDirectImportWhenAcceptedNotificationConsumedCronEvent() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-wait-direct-' );
		$runner = new RecordingProcessRunner( [
			$this->helperSuccessProcess( $this->waitingExportQueueState() ),
			$this->helperSuccessProcess( $this->slaveCronState( false, true ) ),
			$this->helperSuccessProcess( [
				'master_url' => 'http://wordpress-master',
				'import_id' => 'slave-import-id',
			] ),
			$this->helperSuccessProcess( $this->postExportQueueState() ),
		] );
		$manager = new CrossSitePairManager( $runner );

		$result = $this->invokePrivate( $manager, 'waitForSlaveImportCompletion', [ $root ] );

		$this->assertSame( 'idle', $result[ 'rows' ][ 0 ][ 'queue_status' ] );
		$directImportCommand = $this->findProcessCommandContaining( $runner, 'run-import-from-master' );
		$this->assertContains( 'eval-file', $directImportCommand );
		$this->assertContains( 'run-import-from-master', $directImportCommand );
		$this->assertSame(
			[
				'master_url' => 'http://wordpress-master',
				'import_id' => 'slave-import-id',
			],
			$manager->lastDiagnostics()[ 'slave_direct_import' ]
		);
	}

	public function testSlaveImportWaitFailsWhenSlaveNotificationWasNotAccepted() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-wait-not-accepted-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				$this->helperSuccessProcess( $this->waitingExportQueueState() ),
				$this->helperSuccessProcess( $this->slaveCronState( false, false ) ),
			] )
		);

		try {
			$this->invokePrivate( $manager, 'waitForSlaveImportCompletion', [ $root ] );
			$this->fail( 'Expected rejected slave notification failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Slave did not accept the master import notification; no import event or notify cooldown was visible.',
				$exception->getMessage()
			);
		}
	}

	public function testSlaveImportWaitSurfacesDirectImportFailure() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-wait-direct-failure-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				$this->helperSuccessProcess( $this->waitingExportQueueState() ),
				$this->helperSuccessProcess( $this->slaveCronState( false, true ) ),
				$this->helperFailureProcess( 'Master export returned HTTP 403.' ),
			] )
		);

		try {
			$this->invokePrivate( $manager, 'waitForSlaveImportCompletion', [ $root ] );
			$this->fail( 'Expected direct import failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Slave direct import from master failed: Master export returned HTTP 403.',
				$exception->getMessage()
			);
		}
	}

	public function testMasterQueueProcessingAcceptsAlreadyCompletedSlaveExport() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-queue-already-exported-' );
		$runner = new RecordingProcessRunner( [
			$this->helperSuccessProcess( $this->dueMasterQueueState() ),
			[ 'exit_code' => 0 ],
			$this->helperSuccessProcess( $this->postExportQueueState() ),
		] );
		$manager = new CrossSitePairManager( $runner );

		$this->invokePrivate( $manager, 'processMasterSitesQueue', [ $root ] );

		$this->assertSame(
			$this->postExportQueueState(),
			$manager->lastDiagnostics()[ 'master_queue_after_notify_dispatch' ]
		);
		$queueCommand = $this->findProcessCommandContaining( $runner, 'cron event run shield-plugin-importexport-sites-queue' );
		$this->assertContains( 'shield-plugin-importexport-sites-queue', $queueCommand );
	}

	public function testPostNotifyDispatchQueueStateStillRejectsStaleExportCompletion() :void {
		$manager = new CrossSitePairManager();
		$state = $this->postExportQueueState();
		$state[ 'rows' ][ 0 ][ 'last_export_success_at' ] = 5;

		try {
			$this->invokePrivate( $manager, 'assertPostNotifyDispatchQueueState', [ $state ] );
			$this->fail( 'Expected stale export completion failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Master DB-backed site queue did not record a new export success after notify dispatch.',
				$exception->getMessage()
			);
		}
	}

	public function testPostNotifyDispatchQueueStateRejectsCompletedExportWithoutRecordedNotifyDispatch() :void {
		$manager = new CrossSitePairManager();
		$state = $this->postExportQueueState();
		$state[ 'rows' ][ 0 ][ 'last_ping_success_at' ] = 0;

		try {
			$this->invokePrivate( $manager, 'assertPostNotifyDispatchQueueState', [ $state ] );
			$this->fail( 'Expected missing notify dispatch failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Master DB-backed site queue did not record notify dispatch before export.',
				$exception->getMessage()
			);
		}
	}

	public function testPrepareSuppressesSubprocessOutputByDefault() :void {
		$root = $this->createCrossSiteProjectRoot();
		$runner = new CrossSitePrepareProcessRunner();
		$docker = new RecordingDockerComposeExecutor();
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = $this->buildPairManagerForPrepareContract( $runner, $docker, $refresher );

		$this->runPrepareQuietly( $manager, $root, 'warm', false );

		$this->assertNotEmpty( $docker->calls );
		$this->assertSame( [ 'up', '-d', '--wait', '--wait-timeout', '60', 'db' ], $docker->calls[ 0 ][ 'sub_command' ] );
		$composeEnv = $this->assertHasEnvOverrides( $docker->calls[ 0 ] );
		$this->assertCrossSiteReusableLabelEnv( $composeEnv );
		foreach ( $docker->calls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ] );
			$this->assertFalse( $call[ 'show_docker_output' ] );
			$this->assertSameDockerLabelEnvironment( $composeEnv, $this->assertHasEnvOverrides( $call ) );
		}
		$this->assertNotEmpty( $runner->calls );
		$this->assertMysqlTcpCommand( $this->findProcessCommandContaining( $runner, 'mysqladmin ping' ), 'mysqladmin' );
		$this->assertMysqlTcpCommand( $this->findProcessCommandContaining( $runner, 'SELECT 1' ), 'mysql' );
		$this->assertMysqlTcpCommand( $this->findProcessCommandContaining( $runner, 'DROP DATABASE IF EXISTS `shield_cross_site_master`' ), 'mysql' );
		foreach ( $runner->calls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ], \implode( ' ', $call[ 'command' ] ) );
			$this->assertSameDockerLabelEnvironment( $composeEnv, $this->assertHasEnvOverrides( $call ) );
		}
		$this->assertNotEmpty( $refresher->refreshCalls );
		foreach ( $refresher->refreshCalls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ] );
		}
	}

	public function testPrepareShowsSetupOutputOnlyWhenExplicitlyRequested() :void {
		$root = $this->createCrossSiteProjectRoot();
		$runner = new RecordingProcessRunner();
		$docker = new RecordingDockerComposeExecutor();
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = $this->buildPairManagerForPrepareContract( $runner, $docker, $refresher );

		$this->runPrepareQuietly( $manager, $root, 'warm', true );

		$this->assertNotEmpty( $docker->calls );
		foreach ( $docker->calls as $call ) {
			$this->assertFalse( $call[ 'has_output_callback' ] );
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
		$this->assertNotEmpty( $refresher->refreshCalls );
		foreach ( $refresher->refreshCalls as $call ) {
			$this->assertFalse( $call[ 'has_output_callback' ] );
		}

		$this->assertNotEmpty( $runner->calls );
		$readinessCalls = [];
		foreach ( $runner->calls as $call ) {
			if ( $this->isInternalHttpReadinessCall( $call ) ) {
				$readinessCalls[] = $call;
				continue;
			}
			$this->assertFalse( $call[ 'has_output_callback' ], \implode( ' ', $call[ 'command' ] ) );
		}
		$this->assertCount( 2, $readinessCalls );
		foreach ( $readinessCalls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ], \implode( ' ', $call[ 'command' ] ) );
		}

		$provisionCalls = \array_values( \array_filter(
			$runner->calls,
			static fn( array $call ) :bool => \in_array( '/app/tests/docker/provision-local-site.sh', $call[ 'command' ], true )
		) );
		$this->assertCount( 2, $provisionCalls );
		foreach ( $provisionCalls as $call ) {
			$this->assertFalse( $call[ 'has_output_callback' ] );
		}
	}

	public function testWpCliFailureDiagnosticsRemoveDockerStatusNoise() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-helper-docker-noise-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				[
					'exit_code' => 1,
					'stdout' => '',
					'stderr' => " Container shield-cross-site-db-1 Running \n"
						." Container shield-cross-site-db-1 Waiting \n"
						."The import encountered an error.\n",
				],
			] )
		);

		try {
			$this->invokePrivate( $manager, 'runHelper', [ $root, 'slave', 'apply-corpus' ] );
			$this->fail( 'Expected WP-CLI failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'The import encountered an error.', $exception->getMessage() );
			$this->assertStringNotContainsString( 'Container shield-cross-site', $exception->getMessage() );
		}
	}

	public function testPrepareDockerFailureReportsCommandWithoutDumpingOutput() :void {
		$root = $this->createCrossSiteProjectRoot();
		$runner = new RecordingProcessRunner();
		$docker = new RecordingDockerComposeExecutor( [ 7 ] );
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = $this->buildPairManagerForPrepareContract( $runner, $docker, $refresher );

		try {
			$this->runPrepareQuietly( $manager, $root, 'warm', false );
			$this->fail( 'Expected Docker compose failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$message = $exception->getMessage();
			$this->assertStringContainsString( 'Failed to start the cross-site Docker database service.', $message );
			$this->assertStringContainsString( 'Compose project: shield-cross-site', $message );
			$this->assertStringContainsString( 'Exit code: 7', $message );
			$this->assertStringContainsString(
				'Command: docker compose -p shield-cross-site -f tests/docker/docker-compose.cross-site.yml up -d --wait --wait-timeout 60 db',
				$message
			);
			$this->assertStringNotContainsString( 'Container shield-cross-site', $message );
		}
	}

	private function findProcessCommandContaining( RecordingProcessRunner $processRunner, string $fragment ) :array {
		foreach ( $processRunner->calls as $call ) {
			if ( \strpos( \implode( ' ', $call[ 'command' ] ), $fragment ) !== false ) {
				return $call[ 'command' ];
			}
		}

		$this->fail( 'Process command fragment not found: '.$fragment );
	}

	/**
	 * @param string[] $command
	 */
	private function assertMysqlTcpCommand( array $command, string $binary ) :void {
		$this->assertContains( $binary, $command );
		$this->assertContains( '--protocol=tcp', $command );
		$this->assertContains( '-h', $command );
		$this->assertContains( '127.0.0.1', $command );
	}

	/**
	 * @return array{exit_code:int,stdout:string}
	 */
	private function helperSuccessProcess( array $data ) :array {
		return [
			'exit_code' => 0,
			'stdout' => \json_encode( [
				'ok' => true,
				'data' => $data,
			], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR )."\n",
		];
	}

	/**
	 * @return array{exit_code:int,stdout:string}
	 */
	private function helperFailureProcess( string $message ) :array {
		return [
			'exit_code' => 1,
			'stdout' => \json_encode( [
				'ok' => false,
				'error' => [
					'message' => $message,
				],
			], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR )."\n",
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function waitingExportQueueState() :array {
		return [
			'rows' => [
				[
					'url' => 'http://wordpress-slave',
					'queue_status' => 'waiting_export',
					'last_ping_success_at' => 10,
					'last_export_request_at' => 0,
					'last_export_success_at' => 5,
					'last_export_result_code' => 'success',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function dueMasterQueueState() :array {
		return [
			'queue_hook' => 'shield-plugin-importexport-sites-queue',
			'queue_scheduled' => true,
			'due_count' => 1,
			'rows' => [
				[
					'url' => 'http://wordpress-slave',
					'queue_status' => 'queued',
					'last_ping_success_at' => 0,
					'last_export_request_at' => 5,
					'last_export_success_at' => 5,
					'last_export_result_code' => 'success',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function postExportQueueState() :array {
		return [
			'rows' => [
				[
					'url' => 'http://wordpress-slave',
					'queue_status' => 'idle',
					'last_ping_success_at' => 10,
					'last_export_request_at' => 20,
					'last_export_success_at' => 30,
					'last_export_result_code' => 'success',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function slaveCronState( bool $importScheduled, bool $notifyCooldownActive ) :array {
		return [
			'import_hook' => 'shield-plugin-importexport-update-notified',
			'import_scheduled' => $importScheduled,
			'notify_hook' => 'shield-plugin-importexport-notify',
			'notify_scheduled' => false,
			'notify_cooldown_active' => $notifyCooldownActive,
			'queue_hook' => 'shield-plugin-importexport-sites-queue',
			'queue_scheduled' => false,
			'master_url' => 'http://wordpress-master',
			'import_id' => 'slave-import-id',
		];
	}

	private function isInternalHttpReadinessCall( array $call ) :bool {
		if ( !\in_array( 'eval', $call[ 'command' ], true ) ) {
			return false;
		}
		foreach ( $call[ 'command' ] as $part ) {
			if ( \is_string( $part ) && \str_contains( $part, 'wp_remote_get' ) ) {
				return true;
			}
		}
		return false;
	}

	private function buildPairManagerForPrepareContract(
		RecordingProcessRunner $runner,
		RecordingDockerComposeExecutor $docker,
		CrossSiteRuntimeRefresherRecorder $refresher
	) :CrossSitePairManager {
		return new CrossSitePairManager(
			$runner,
			new RecordingTestingEnvironmentResolver( '8.2' ),
			$docker,
			$refresher,
			new CrossSiteSetupCacheCoordinatorStub()
		);
	}

	private function createCrossSiteProjectRoot() :string {
		$root = $this->createTrackedTempDir( 'shield-cross-site-prepare-' );
		foreach ( [
			[ 'vendor' ],
			[ 'assets', 'dist' ],
			[ 'tests', 'docker' ],
		] as $dirParts ) {
			$dir = Path::join( $root, ...$dirParts );
			if ( !\is_dir( $dir ) ) {
				\mkdir( $dir, 0777, true );
			}
		}
		foreach ( [
			[ 'vendor', 'autoload.php' ],
			[ 'icwp-wpsf.php' ],
			[ 'tests', 'docker', 'provision-local-site.sh' ],
		] as $fileParts ) {
			\file_put_contents( Path::join( $root, ...$fileParts ), '<?php' );
		}
		return $root;
	}

	private function runPrepareQuietly(
		CrossSitePairManager $manager,
		string $root,
		string $mode,
		bool $showSetupOutput
	) :void {
		\ob_start();
		try {
			$manager->prepare( $root, $mode, $showSetupOutput );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function assertCrossSiteReusableLabelEnv( array $env ) :void {
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_LABEL_HARNESS', 'shield-plugin-cross-site' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_LABEL_LANE', 'cross-site' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_CONTAINER_RUN_ID', 'shield-plugin-cross-site-reusable' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_VOLUME_RUN_ID', 'shield-plugin-cross-site-reusable' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_CONTAINER_LIFECYCLE', 'reusable' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_VOLUME_LIFECYCLE', 'reusable' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_CONTAINER_EXPIRES_AT', '2037-12-31T23:59:59+00:00' );
		$this->assertEnvValue( $env, 'SHIELD_DOCKER_VOLUME_EXPIRES_AT', '2037-12-31T23:59:59+00:00' );
	}

	private function assertSameDockerLabelEnvironment( array $expected, array $actual ) :void {
		foreach ( $this->dockerLabelEnvKeys() as $key ) {
			$this->assertArrayHasKey( $key, $expected );
			$this->assertArrayHasKey( $key, $actual );
			$this->assertSame( $expected[ $key ], $actual[ $key ], $key );
		}
	}

	private function assertEnvValue( array $env, string $key, string $expectedValue ) :void {
		$this->assertArrayHasKey( $key, $env );
		$this->assertSame( $expectedValue, $env[ $key ], $key );
	}

	private function assertHasEnvOverrides( array $call ) :array {
		$this->assertArrayHasKey( 'env_overrides', $call );
		$this->assertIsArray( $call[ 'env_overrides' ] );
		return $call[ 'env_overrides' ];
	}

	/**
	 * @return string[]
	 */
	private function dockerLabelEnvKeys() :array {
		return [
			'SHIELD_DOCKER_LABEL_HARNESS',
			'SHIELD_DOCKER_LABEL_LANE',
			'SHIELD_DOCKER_CONTAINER_RUN_ID',
			'SHIELD_DOCKER_CONTAINER_LIFECYCLE',
			'SHIELD_DOCKER_CONTAINER_EXPIRES_AT',
			'SHIELD_DOCKER_VOLUME_RUN_ID',
			'SHIELD_DOCKER_VOLUME_LIFECYCLE',
			'SHIELD_DOCKER_VOLUME_EXPIRES_AT',
		];
	}

	private function waitForUnixSecondToChange() :void {
		$startedAt = \time();
		do {
			\usleep( 100000 );
		} while ( \time() === $startedAt );
	}

	/**
	 * @param mixed[] $args
	 * @return mixed
	 */
	private function invokePrivate( object $object, string $methodName, array $args = [] ) {
		$method = new \ReflectionMethod( $object, $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}
}

class CrossSiteRuntimeRefresherRecorder extends LocalSiteRuntimeRefresher {

	/** @var array<int,array{container_id:string,has_output_callback:bool,host_manifest:?array}> */
	public array $refreshCalls = [];

	public function resolveServiceContainerId(
		string $rootDir,
		array $composeFiles,
		string $serviceName,
		array $envOverrides
	) :string {
		return $serviceName.'-container';
	}

	public function refresh( string $rootDir, string $containerId, ?callable $onOutput = null, ?array $hostManifest = null ) :void {
		$this->refreshCalls[] = [
			'container_id'         => $containerId,
			'has_output_callback' => $onOutput !== null,
			'host_manifest'       => $hostManifest,
		];
	}
}

class CrossSitePrepareProcessRunner extends RecordingProcessRunner {

	private bool $delayedBeforeReadiness = false;

	public function run(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :\Symfony\Component\Process\Process {
		$process = parent::run( $command, $workingDir, $onOutput, $envOverrides );
		if ( !$this->delayedBeforeReadiness
			 && \in_array( 'wp-cli-slave', $command, true )
			 && \in_array( '/app/tests/docker/provision-local-site.sh', $command, true ) ) {
			$this->delayedBeforeReadiness = true;
			$startedAt = \time();
			do {
				\usleep( 100000 );
			} while ( \time() === $startedAt );
		}

		return $process;
	}
}

class CrossSiteSetupCacheCoordinatorStub extends SourceSetupCacheCoordinator {

	public function evaluateAnalyzeSetup( string $rootDir, bool $refreshSetup = false ) :array {
		return [
			'needs_build_config' => false,
			'fingerprint' => 'cross-site-test',
		];
	}
}

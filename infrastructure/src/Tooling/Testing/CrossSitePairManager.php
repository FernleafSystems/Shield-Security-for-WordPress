<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class CrossSitePairManager {

	private const COMPOSE_FILE = 'tests/docker/docker-compose.cross-site.yml';
	private const COMPOSE_PROJECT_NAME = 'shield-cross-site';
	private const DB_SERVICE_NAME = 'db';
	private const DB_ROOT_PASSWORD = 'testpass';
	private const MASTER = 'master';
	private const SLAVE = 'slave';
	private const MASTER_WORDPRESS_SERVICE = 'wordpress-master';
	private const SLAVE_WORDPRESS_SERVICE = 'wordpress-slave';
	private const MASTER_WPCLI_SERVICE = 'wp-cli-master';
	private const SLAVE_WPCLI_SERVICE = 'wp-cli-slave';
	private const MASTER_INTERNAL_URL = 'http://wordpress-master';
	private const SLAVE_INTERNAL_URL = 'http://wordpress-slave';
	private const MASTER_DB_NAME = 'shield_cross_site_master';
	private const SLAVE_DB_NAME = 'shield_cross_site_slave';
	private const MASTER_HOST_PORT = '8892';
	private const SLAVE_HOST_PORT = '8893';
	private const HELPER_FILE = '/app/tests/Helpers/CrossSite/CrossSiteRuntime.php';
	private const STATUS_ACTIVE = 'active';
	private const QUEUE_IDLE = 'idle';
	private const QUEUE_QUEUED = 'queued';
	private const QUEUE_WAITING_EXPORT = 'waiting_export';
	private const EXPORT_RESULT_SUCCESS = 'success';

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalSiteRuntimeRefresher $runtimeRefresher;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	private string $lastStage = 'not started';

	/** @var array<string,mixed> */
	private array $lastDiagnostics = [];

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteRuntimeRefresher $runtimeRefresher = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->runtimeRefresher = $runtimeRefresher ?? new LocalSiteRuntimeRefresher( $this->processRunner );
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
	}

	public function prepare( string $rootDir, string $mode, bool $showSetupOutput = false ) :void {
		$this->lastDiagnostics = [];
		$onOutput = $this->setupOutputHandler( $showSetupOutput );
		$showDockerOutput = $showSetupOutput;

		$this->stage( 'preflight' );
		$this->runPreflightChecks( $rootDir, $onOutput );
		$envOverrides = $this->buildRuntimeEnvOverrides( $rootDir );
		$composeFiles = $this->buildComposeFiles();

		if ( $mode === 'clean' ) {
			$this->stage( 'clean cross-site pair' );
			$exitCode = $this->dockerComposeExecutor->run(
				$rootDir,
				$composeFiles,
				[ 'down', '-v', '--remove-orphans' ],
				$envOverrides,
				$onOutput,
				$showDockerOutput
			);
			if ( $exitCode !== 0 ) {
				throw $this->composeFailureException(
					'Failed to remove the previous cross-site containers and volumes.',
					[ 'down', '-v', '--remove-orphans' ],
					$exitCode
				);
			}
		}
		elseif ( $mode !== 'warm' ) {
			throw new \InvalidArgumentException( 'Cross-site lane mode must be "clean" or "warm".' );
		}

		$this->stage( 'start cross-site database' );
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			[
				'up',
				'-d',
				self::DB_SERVICE_NAME,
			],
			$envOverrides,
			$onOutput,
			$showDockerOutput
		);
		if ( $exitCode !== 0 ) {
			throw $this->composeFailureException(
				'Failed to start the cross-site Docker database service.',
				[ 'up', '-d', self::DB_SERVICE_NAME ],
				$exitCode
			);
		}

		$this->stage( 'create cross-site databases' );
		$this->createDatabases( $rootDir, $envOverrides, $onOutput );

		$this->stage( 'start cross-site services' );
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			$composeFiles,
			[
				'up',
				'-d',
				self::MASTER_WORDPRESS_SERVICE,
				self::SLAVE_WORDPRESS_SERVICE,
			],
			$envOverrides,
			$onOutput,
			$showDockerOutput
		);
		if ( $exitCode !== 0 ) {
			throw $this->composeFailureException(
				'Failed to start the cross-site WordPress services.',
				[ 'up', '-d', self::MASTER_WORDPRESS_SERVICE, self::SLAVE_WORDPRESS_SERVICE ],
				$exitCode
			);
		}

		$this->stage( 'refresh master runtime' );
		$this->refreshRuntime( $rootDir, self::MASTER_WORDPRESS_SERVICE, $envOverrides, $onOutput );
		$this->stage( 'refresh slave runtime' );
		$this->refreshRuntime( $rootDir, self::SLAVE_WORDPRESS_SERVICE, $envOverrides, $onOutput );

		$this->stage( 'provision master site' );
		$this->runProvision( $rootDir, self::MASTER, $envOverrides, $onOutput );
		$this->stage( 'provision slave site' );
		$this->runProvision( $rootDir, self::SLAVE, $envOverrides, $onOutput );
		$this->stage( 'wait for master internal HTTP' );
		$this->waitForInternalHttpReady( $rootDir, self::SLAVE, self::MASTER_INTERNAL_URL.'/wp-login.php' );
		$this->stage( 'wait for slave internal HTTP' );
		$this->waitForInternalHttpReady( $rootDir, self::MASTER, self::SLAVE_INTERNAL_URL.'/wp-login.php' );
	}

	public function runImportExportScenario( string $rootDir ) :void {
		$this->stage( 'setup cross-site runtime state' );
		$this->runHelper( $rootDir, self::MASTER, 'setup', [ 'role' => self::MASTER ] );
		$this->runHelper( $rootDir, self::SLAVE, 'setup', [ 'role' => self::SLAVE ] );

		$this->stage( 'verify legacy import/export registry migration' );
		$legacyMigration = $this->runHelper( $rootDir, self::MASTER, 'legacy-migration-check', [
			'slave_url' => self::SLAVE_INTERNAL_URL,
		] );
		$this->lastDiagnostics[ 'legacy_migration' ] = $legacyMigration;
		$this->assertLegacyMigration( $legacyMigration );

		$this->stage( 'reset cross-site runtime state after legacy migration check' );
		$this->runHelper( $rootDir, self::MASTER, 'setup', [ 'role' => self::MASTER ] );
		$this->runHelper( $rootDir, self::SLAVE, 'setup', [ 'role' => self::SLAVE ] );

		$this->stage( 'read master import secret' );
		$secret = (string)( $this->runHelper( $rootDir, self::MASTER, 'secret' )[ 'secret' ] ?? '' );
		if ( $secret === '' ) {
			throw new \RuntimeException( 'Master import/export secret was empty.' );
		}

		$this->stage( 'connect slave to master' );
		$this->wpCapture( $rootDir, self::SLAVE, [
			'shield',
			'import',
			'--source='.self::MASTER_INTERNAL_URL,
			'--site-secret='.$secret,
			'--slave=add',
			'--force',
		] );

		$this->stage( 'assert cross-site network state' );
		$network = [
			'master' => $this->runHelper( $rootDir, self::MASTER, 'state' ),
			'slave'  => $this->runHelper( $rootDir, self::SLAVE, 'state' ),
		];
		$this->lastDiagnostics[ 'network' ] = $network;
		$masterState = $network[ 'master' ];
		$slaveState = $network[ 'slave' ];
		if ( !\is_array( $masterState )
			 || !\in_array( self::SLAVE_INTERNAL_URL, $masterState[ 'whitelist' ] ?? [], true ) ) {
			throw new \RuntimeException( 'Master whitelist does not contain the slave internal URL.' );
		}
		$this->assertRegistryContainsSlave( $masterState, 'after slave connection' );
		if ( !\is_array( $slaveState ) || ( $slaveState[ 'master_url' ] ?? '' ) !== self::MASTER_INTERNAL_URL ) {
			throw new \RuntimeException( 'Slave master URL was not set to the master internal URL.' );
		}

		$this->stage( 'apply master option corpus' );
		$corpus = $this->runHelper( $rootDir, self::MASTER, 'apply-corpus' );
		$this->lastDiagnostics[ 'corpus' ] = $this->summariseCorpusDiagnostics( $corpus );

		$this->stage( 'run legacy notify compatibility hook' );
		$this->lastDiagnostics[ 'legacy_notify' ] = $this->runHelper( $rootDir, self::MASTER, 'run-notify-hook' );

		$this->stage( 'process master DB-backed site queue' );
		$this->processMasterSitesQueue( $rootDir );

		$this->stage( 'run slave import cron' );
		$slaveCron = $this->runHelper( $rootDir, self::SLAVE, 'cron-state' );
		$this->lastDiagnostics[ 'slave_cron' ] = $slaveCron;
		$importHook = (string)( $slaveCron[ 'import_hook' ] ?? '' );
		if ( empty( $slaveCron[ 'import_scheduled' ] ) || $importHook === '' ) {
			throw new \RuntimeException( 'Slave import cron was not scheduled after master notification.' );
		}
		$this->wpCapture( $rootDir, self::SLAVE, [ 'cron', 'event', 'run', $importHook ] );

		$this->stage( 'assert master export sync completion' );
		$queueAfterImport = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->lastDiagnostics[ 'master_queue_after_import' ] = $queueAfterImport;
		$this->assertPostExportQueueState( $queueAfterImport );

		$this->stage( 'compare exported option payloads' );
		$this->assertExportsMatch( $rootDir );
	}

	public function lastStage() :string {
		return $this->lastStage;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function lastDiagnostics() :array {
		return $this->lastDiagnostics;
	}

	public function composeProjectName() :string {
		return self::COMPOSE_PROJECT_NAME;
	}

	public function masterInternalUrl() :string {
		return self::MASTER_INTERNAL_URL;
	}

	public function slaveInternalUrl() :string {
		return self::SLAVE_INTERNAL_URL;
	}

	public function masterDbName() :string {
		return self::MASTER_DB_NAME;
	}

	public function slaveDbName() :string {
		return self::SLAVE_DB_NAME;
	}

	private function processMasterSitesQueue( string $rootDir ) :void {
		$queue = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->lastDiagnostics[ 'master_queue_before' ] = $queue;
		$queueHook = (string)( $queue[ 'queue_hook' ] ?? '' );
		if ( empty( $queue[ 'due_count' ] ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue had no due rows for the slave.' );
		}
		if ( empty( $queue[ 'queue_scheduled' ] ) || $queueHook === '' ) {
			throw new \RuntimeException( 'Master DB-backed site queue had due rows but no scheduled queue hook.' );
		}
		$this->wpCapture( $rootDir, self::MASTER, [ 'cron', 'event', 'run', $queueHook ] );

		$queueAfter = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->lastDiagnostics[ 'master_queue_after_ping' ] = $queueAfter;
		$this->assertPostPingQueueState( $queueAfter );
	}

	private function assertLegacyMigration( array $legacyMigration ) :void {
		$rows = (array)( $legacyMigration[ 'rows' ] ?? [] );
		$slaveUrl = (string)( $legacyMigration[ 'slave_url' ] ?? '' );
		$extraUrl = (string)( $legacyMigration[ 'extra_url' ] ?? '' );
		$slave = $this->findRegistryRow( $rows, $slaveUrl );
		$extra = $this->findRegistryRow( $rows, $extraUrl );

		if ( !\is_array( $slave ) || !\is_array( $extra ) ) {
			throw new \RuntimeException( 'Legacy registry migration did not create active rows for fallback URLs.' );
		}
		if ( ( $slave[ 'import_id' ] ?? '' ) !== 'legacy-slave-id'
			 || ( $extra[ 'import_id' ] ?? '' ) !== 'legacy-extra-id' ) {
			throw new \RuntimeException( 'Legacy registry migration did not preserve import IDs.' );
		}
		if ( ( $slave[ 'status' ] ?? '' ) !== self::STATUS_ACTIVE
			 || ( $slave[ 'queue_status' ] ?? '' ) !== self::QUEUE_QUEUED
			 || (int)( $slave[ 'next_ping_at' ] ?? 0 ) <= 0 ) {
			throw new \RuntimeException( 'Legacy registry migration did not mark the matching fallback URL due.' );
		}
		if ( !empty( $legacyMigration[ 'unknown_old_queue_row_exists' ] ) ) {
			throw new \RuntimeException( 'Legacy registry migration created a row from an unknown old queue URL.' );
		}
		if ( !empty( $legacyMigration[ 'legacy_batch_count' ] ) ) {
			throw new \RuntimeException( 'Legacy registry migration did not clear old queue batches.' );
		}

		$whitelist = (array)( $legacyMigration[ 'whitelist' ] ?? [] );
		if ( !\in_array( $slaveUrl, $whitelist, true ) || !\in_array( $extraUrl, $whitelist, true ) ) {
			throw new \RuntimeException( 'Legacy registry migration did not mirror active rows back to the fallback whitelist.' );
		}
		$importIds = (array)( $legacyMigration[ 'import_url_ids' ] ?? [] );
		if ( ( $importIds[ \md5( $slaveUrl ) ] ?? '' ) !== 'legacy-slave-id'
			 || ( $importIds[ \md5( $extraUrl ) ] ?? '' ) !== 'legacy-extra-id' ) {
			throw new \RuntimeException( 'Legacy registry migration did not mirror import IDs back to fallback settings.' );
		}
	}

	private function assertRegistryContainsSlave( array $state, string $context ) :void {
		$row = $this->findRegistryRow( (array)( $state[ 'registry' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) || ( $row[ 'status' ] ?? '' ) !== self::STATUS_ACTIVE ) {
			throw new \RuntimeException( 'Master registry does not contain the active slave URL '.$context.'.' );
		}
	}

	private function assertPostPingQueueState( array $queueState ) :void {
		$row = $this->findRegistryRow( (array)( $queueState[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue lost the slave registry row after ping.' );
		}
		if ( ( $row[ 'queue_status' ] ?? '' ) !== self::QUEUE_WAITING_EXPORT ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not wait for slave export after ping.' );
		}
		if ( (int)( $row[ 'last_ping_success_at' ] ?? 0 ) <= 0 ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not record ping success.' );
		}
		if ( (int)( $row[ 'last_export_success_at' ] ?? 0 ) >= (int)( $row[ 'last_ping_success_at' ] ?? 0 ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue counted ping success as export sync success.' );
		}
	}

	private function assertPostExportQueueState( array $queueState ) :void {
		$row = $this->findRegistryRow( (array)( $queueState[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue lost the slave registry row after slave import.' );
		}
		if ( ( $row[ 'queue_status' ] ?? '' ) !== self::QUEUE_IDLE ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not return the slave row to idle after export.' );
		}
		if ( (int)( $row[ 'last_export_request_at' ] ?? 0 ) <= 0
			 || (int)( $row[ 'last_export_success_at' ] ?? 0 ) <= 0 ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not record export request and success.' );
		}
		if ( (int)( $row[ 'last_export_success_at' ] ?? 0 ) <= (int)( $row[ 'last_ping_success_at' ] ?? 0 ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not record a new export success after ping.' );
		}
		if ( ( $row[ 'last_export_result_code' ] ?? '' ) !== self::EXPORT_RESULT_SUCCESS ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not record export success result code.' );
		}
	}

	private function findRegistryRow( array $rows, string $url ) :?array {
		foreach ( $rows as $row ) {
			if ( \is_array( $row ) && ( $row[ 'url' ] ?? '' ) === $url ) {
				return $row;
			}
		}
		return null;
	}

	private function assertExportsMatch( string $rootDir ) :void {
		$masterExport = $this->runHelper( $rootDir, self::MASTER, 'export-options' );
		$slaveExport = $this->runHelper( $rootDir, self::SLAVE, 'export-options' );
		$exceptions = $this->exportComparisonExclusions( $masterExport, $slaveExport );

		$masterOptions = $this->withoutKeys( (array)( $masterExport[ 'options' ] ?? [] ), $exceptions );
		$slaveOptions = $this->withoutKeys( (array)( $slaveExport[ 'options' ] ?? [] ), $exceptions );
		$this->sortRecursive( $masterOptions );
		$this->sortRecursive( $slaveOptions );

		if ( $masterOptions === $slaveOptions ) {
			return;
		}

		$diff = $this->buildOptionsDiff( $masterOptions, $slaveOptions );
		$this->lastDiagnostics[ 'option_diff' ] = $diff;
		throw new \RuntimeException(
			\sprintf(
				'Cross-site option export mismatch. Differing keys: %d. First keys: %s',
				\count( $diff ),
				\implode( ', ', \array_slice( \array_keys( $diff ), 0, 10 ) )
			)
		);
	}

	/**
	 * @param array<string,mixed> $masterExport
	 * @param array<string,mixed> $slaveExport
	 * @return string[]
	 */
	private function exportComparisonExclusions( array $masterExport, array $slaveExport ) :array {
		return \array_values( \array_unique( \array_merge(
			(array)( $masterExport[ 'local_state_exceptions' ] ?? [] ),
			(array)( $slaveExport[ 'local_state_exceptions' ] ?? [] ),
			(array)( $masterExport[ 'runtime_invariant_keys' ] ?? [] ),
			(array)( $slaveExport[ 'runtime_invariant_keys' ] ?? [] )
		) ) );
	}

	/**
	 * @param array<string,mixed> $options
	 * @param string[]           $keys
	 * @return array<string,mixed>
	 */
	private function withoutKeys( array $options, array $keys ) :array {
		foreach ( $keys as $key ) {
			unset( $options[ (string)$key ] );
		}
		return $options;
	}

	/**
	 * @param array<string,mixed> $left
	 * @param array<string,mixed> $right
	 * @return array<string,array{master:mixed,slave:mixed}>
	 */
	private function buildOptionsDiff( array $left, array $right ) :array {
		$diff = [];
		foreach ( \array_unique( \array_merge( \array_keys( $left ), \array_keys( $right ) ) ) as $key ) {
			$leftExists = \array_key_exists( $key, $left );
			$rightExists = \array_key_exists( $key, $right );
			$leftValue = $leftExists ? $left[ $key ] : [ '__missing__' => true ];
			$rightValue = $rightExists ? $right[ $key ] : [ '__missing__' => true ];
			if ( !$leftExists || !$rightExists || \serialize( $leftValue ) !== \serialize( $rightValue ) ) {
				$diff[ $key ] = [
					'master' => $leftValue,
					'slave'  => $rightValue,
				];
			}
		}
		return $diff;
	}

	/**
	 * @param mixed $value
	 */
	private function sortRecursive( &$value ) :void {
		if ( !\is_array( $value ) ) {
			return;
		}
		foreach ( $value as &$item ) {
			$this->sortRecursive( $item );
		}
		unset( $item );
		\ksort( $value );
	}

	/**
	 * @return array<string,int|string[]>
	 */
	private function summariseCorpusDiagnostics( array $corpus ) :array {
		return [
			'applied_count' => \count( (array)( $corpus[ 'applied_keys' ] ?? [] ) ),
			'local_state_exceptions' => \array_values( (array)( $corpus[ 'local_state_exceptions' ] ?? [] ) ),
			'runtime_invariant_keys' => \array_values( (array)( $corpus[ 'runtime_invariant_keys' ] ?? [] ) ),
			'normalised_count' => \count( (array)( $corpus[ 'normalised_keys' ] ?? [] ) ),
		];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function runHelper( string $rootDir, string $site, string $action, array $payload = [] ) :array {
		$encodedPayload = \base64_encode( \json_encode( $payload, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) );
		$captured = $this->wpCapture( $rootDir, $site, [
			'eval-file',
			self::HELPER_FILE,
			$action,
			$encodedPayload,
		], false );

		try {
			$decoded = $this->decodeHelperOutput( $captured[ 'stdout' ] );
		}
		catch ( \RuntimeException $exception ) {
			if ( $captured[ 'exit_code' ] !== 0 ) {
				throw $this->wpCliFailureException( $site, $captured );
			}
			throw $exception;
		}
		if ( empty( $decoded[ 'ok' ] ) ) {
			throw new \RuntimeException( (string)( $decoded[ 'error' ][ 'message' ] ?? 'Cross-site helper failed.' ) );
		}
		if ( $captured[ 'exit_code' ] !== 0 ) {
			throw $this->wpCliFailureException( $site, $captured );
		}

		$data = $decoded[ 'data' ] ?? [];
		if ( !\is_array( $data ) ) {
			throw new \RuntimeException( 'Cross-site helper returned a non-array data payload.' );
		}
		return $data;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeHelperOutput( string $stdout ) :array {
		$lines = \array_reverse( \preg_split( '/\R/', \trim( $stdout ) ) ?: [] );
		foreach ( $lines as $line ) {
			$line = \trim( $line );
			if ( $line === '' || $line[ 0 ] !== '{' ) {
				continue;
			}
			$decoded = \json_decode( $line, true );
			if ( \is_array( $decoded ) ) {
				return $decoded;
			}
		}
		throw new \RuntimeException(
			'Cross-site helper did not return a JSON object. Output: '.$this->trimDiagnosticBuffer( $stdout )
		);
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return array{stdout:string,stderr:string,exit_code:int}
	 */
	private function wpCapture( string $rootDir, string $site, array $wpCliArgs, bool $throwOnFailure = true ) :array {
		$captured = $this->wpCaptureRaw( $rootDir, $site, $wpCliArgs );
		if ( $throwOnFailure && $captured[ 'exit_code' ] !== 0 ) {
			throw $this->wpCliFailureException( $site, $captured );
		}

		return $captured;
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return array{stdout:string,stderr:string,exit_code:int}
	 */
	private function wpCaptureRaw( string $rootDir, string $site, array $wpCliArgs ) :array {
		$stdout = '';
		$stderr = '';
		$collector = static function ( string $type, string $buffer ) use ( &$stdout, &$stderr ) :void {
			if ( $type === Process::ERR ) {
				$stderr .= $buffer;
			}
			else {
				$stdout .= $buffer;
			}
		};

		$process = $this->processRunner->run(
			$this->buildWpCliCommand( $site, $wpCliArgs ),
			$rootDir,
			$collector,
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
		$exitCode = $process->getExitCode() ?? 1;

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
			'exit_code' => $exitCode,
		];
	}

	/**
	 * @param array{stdout:string,stderr:string,exit_code:int} $captured
	 */
	private function wpCliFailureException( string $site, array $captured ) :\RuntimeException {
		$stderr = $this->removeDockerStatusNoise( $captured[ 'stderr' ] );
		$stdout = $this->removeDockerStatusNoise( $captured[ 'stdout' ] );
		$details = \trim( $stderr ) !== '' ? \trim( $stderr ) : \trim( $stdout );
		return new \RuntimeException(
			\sprintf(
				'WP-CLI command failed on %s with exit code %d. %s',
				$site,
				$captured[ 'exit_code' ],
				$this->trimDiagnosticBuffer( $details )
			)
		);
	}

	private function removeDockerStatusNoise( string $buffer ) :string {
		$lines = \preg_split( '/\R/', $buffer ) ?: [];
		$kept = \array_filter(
			$lines,
			static function ( string $line ) :bool {
				return \preg_match(
					'/^\s*Container\s+shield-cross-site-[^\r\n]+\s+(?:Running|Waiting|Healthy|Creating|Created|Starting|Started|Stopping|Stopped|Removing|Removed)\s*$/',
					$line
				) !== 1;
			}
		);
		return \trim( \implode( \PHP_EOL, $kept ) );
	}

	private function trimDiagnosticBuffer( string $buffer ) :string {
		$buffer = \trim( $buffer );
		if ( \strlen( $buffer ) <= 1200 ) {
			return $buffer;
		}
		return \substr( $buffer, 0, 1200 ).'...';
	}

	private function createDatabases( string $rootDir, array $envOverrides, ?callable $onOutput ) :void {
		$this->waitForDatabaseReady( $rootDir, $envOverrides, $onOutput );
		$command = \array_merge(
			$this->buildComposeCommandForExecution( [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			[ 'mysql', '-h', '127.0.0.1', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '-e', $this->buildResetDatabasesSql() ]
		);
		$process = $this->processRunner->run(
			$command,
			$rootDir,
			$onOutput,
			$envOverrides
		);
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			throw $this->commandFailureException(
				'Failed to create cross-site databases.',
				$command,
				$exitCode,
				$process->getOutput(),
				$process->getErrorOutput()
			);
		}
	}

	private function buildResetDatabasesSql() :string {
		return \sprintf(
			'DROP DATABASE IF EXISTS `%1$s`; CREATE DATABASE `%1$s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; '.
			'DROP DATABASE IF EXISTS `%2$s`; CREATE DATABASE `%2$s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
			self::MASTER_DB_NAME,
			self::SLAVE_DB_NAME
		);
	}

	private function waitForDatabaseReady( string $rootDir, array $envOverrides, ?callable $onOutput ) :void {
		$command = \array_merge(
			$this->buildComposeCommandForExecution( [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			[ 'mysqladmin', 'ping', '-h', '127.0.0.1', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '--silent' ]
		);
		$startedAt = \time();
		do {
			$process = $this->processRunner->run(
				$command,
				$rootDir,
				$onOutput,
				$envOverrides
			);
			if ( ( $process->getExitCode() ?? 1 ) === 0 ) {
				return;
			}
			\usleep( 500000 );
		} while ( \time() - $startedAt < 60 );

		throw $this->commandFailureException(
			'Cross-site MySQL did not become ready within 60 seconds.',
			$command,
			$process->getExitCode() ?? 1,
			$process->getOutput(),
			$process->getErrorOutput()
		);
	}

	private function waitForInternalHttpReady( string $rootDir, string $requestingSite, string $url ) :void {
		$script = \sprintf(
			<<<'PHP'
$response = wp_remote_get(%s, [
	'timeout' => 5,
	'redirection' => 0,
]);
if ( is_wp_error($response) ) {
	fwrite(STDERR, $response->get_error_message());
	exit(1);
}
$code = (int)wp_remote_retrieve_response_code($response);
if ( $code < 200 || $code >= 500 ) {
	fwrite(STDERR, 'HTTP '.$code);
	exit(2);
}
PHP,
			\var_export( $url, true )
		);
		$startedAt = \time();
		do {
			$captured = $this->wpCapture( $rootDir, $requestingSite, [ '--skip-plugins', 'eval', $script ], false );
			if ( $captured[ 'exit_code' ] === 0 ) {
				return;
			}
			\sleep( 1 );
		} while ( \time() - $startedAt < 30 );

		throw new \RuntimeException(
			'Cross-site internal HTTP readiness check failed for '.$url.'. '
			.$this->wpCliFailureException( $requestingSite, $captured )->getMessage()
		);
	}

	private function refreshRuntime(
		string $rootDir,
		string $serviceName,
		array $envOverrides,
		?callable $onOutput
	) :void {
		$containerId = $this->runtimeRefresher->resolveServiceContainerId(
			$rootDir,
			$this->buildComposeFiles(),
			$serviceName,
			$envOverrides
		);
		if ( $containerId === '' ) {
			throw new \RuntimeException( 'Could not resolve cross-site WordPress container: '.$serviceName );
		}
		$this->runtimeRefresher->refresh( $rootDir, $containerId, $onOutput );
	}

	private function runProvision( string $rootDir, string $site, array $envOverrides, ?callable $onOutput ) :void {
		$command = $this->buildProvisionCommand( $site );
		$process = $this->processRunner->run(
			$command,
			$rootDir,
			$onOutput,
			$envOverrides
		);
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			throw $this->commandFailureException(
				'Failed to provision cross-site '.$site.' site.',
				$command,
				$exitCode,
				$process->getOutput(),
				$process->getErrorOutput()
			);
		}
	}

	private function runPreflightChecks( string $rootDir, ?callable $onOutput ) :void {
		$this->environmentResolver->assertDockerReady( $rootDir );

		$checks = [
			Path::join( $rootDir, 'vendor', 'autoload.php' )
				=> "Composer dependencies are missing. Run 'composer install'.",
			Path::join( $rootDir, 'assets', 'dist' )
				=> "Compiled assets are missing. Run 'npm install --no-audit --no-fund' and 'npm run build'.",
			Path::join( $rootDir, 'icwp-wpsf.php' )
				=> 'Plugin root file icwp-wpsf.php is missing.',
			Path::join( $rootDir, 'tests', 'docker', 'provision-local-site.sh' )
				=> 'Local site provisioning script is missing.',
		];

		foreach ( $checks as $path => $message ) {
			if ( !\file_exists( $path ) ) {
				throw new \RuntimeException( $message );
			}
		}

		$setup = $this->setupCacheCoordinator->evaluateAnalyzeSetup( $rootDir );
		if ( $setup[ 'needs_build_config' ] ) {
			$process = $this->processRunner->run( [ \PHP_BINARY, './bin/build-config.php' ], $rootDir, $onOutput );
			if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
				throw new \RuntimeException( 'Failed to regenerate plugin.json for cross-site tooling.' );
			}
			$this->setupCacheCoordinator->persistBuildConfigState( $rootDir, $setup[ 'fingerprint' ] );
		}
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildRuntimeEnvOverrides( string $rootDir ) :array {
		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides(
			self::COMPOSE_PROJECT_NAME,
			true
		);
		$envOverrides[ 'PHP_VERSION' ] = $this->environmentResolver->resolvePhpVersion( $rootDir );
		$envOverrides[ 'SHIELD_CROSS_SITE_MASTER_PORT' ] = (string)( \getenv( 'SHIELD_CROSS_SITE_MASTER_PORT' ) ?: self::MASTER_HOST_PORT );
		$envOverrides[ 'SHIELD_CROSS_SITE_SLAVE_PORT' ] = (string)( \getenv( 'SHIELD_CROSS_SITE_SLAVE_PORT' ) ?: self::SLAVE_HOST_PORT );
		return $envOverrides;
	}

	/**
	 * @return string[]
	 */
	private function buildComposeFiles() :array {
		return [
			self::COMPOSE_FILE,
		];
	}

	/**
	 * @return string[]
	 */
	private function buildProvisionCommand( string $site ) :array {
		$definition = $this->siteDefinition( $site );
		$command = $this->buildComposeCommandForExecution( [
			'run',
			'--rm',
			'-T',
		] );
		foreach ( [
			'SHIELD_LOCAL_SITE_URL' => $definition[ 'url' ],
			'SHIELD_LOCAL_SITE_TITLE' => $definition[ 'title' ],
			'SHIELD_LOCAL_SITE_PROFILE' => 'cross-site-'.$site,
			'SHIELD_LOCAL_SITE_ADMIN_USER' => 'admin',
			'SHIELD_LOCAL_SITE_ADMIN_PASSWORD' => 'password',
			'SHIELD_LOCAL_SITE_ADMIN_EMAIL' => 'devnull@example.com',
		] as $name => $value ) {
			$command[] = '-e';
			$command[] = $name.'='.$value;
		}
		return \array_merge( $command, [
			$definition[ 'wp_cli_service' ],
			'sh',
			'/app/tests/docker/provision-local-site.sh',
		] );
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return string[]
	 */
	private function buildWpCliCommand( string $site, array $wpCliArgs ) :array {
		$definition = $this->siteDefinition( $site );
		$command = \array_merge(
			$this->buildComposeCommandForExecution( [
				'run',
				'--rm',
				'-T',
				$definition[ 'wp_cli_service' ],
				'wp',
			] ),
			$wpCliArgs
		);
		if ( !\in_array( '--allow-root', $wpCliArgs, true ) ) {
			$command[] = '--allow-root';
		}
		return $command;
	}

	/**
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function buildComposeCommandForExecution( array $subCommand ) :array {
		return \array_merge(
			[
				'docker',
				'compose',
				'-p',
				self::COMPOSE_PROJECT_NAME,
				'-f',
				self::COMPOSE_FILE,
			],
			$subCommand
		);
	}

	/**
	 * @param string[] $subCommand
	 */
	private function composeFailureException( string $summary, array $subCommand, int $exitCode ) :\RuntimeException {
		return $this->commandFailureException(
			$summary,
			$this->buildComposeCommandForExecution( $subCommand ),
			$exitCode
		);
	}

	/**
	 * @param string[] $command
	 */
	private function commandFailureException(
		string $summary,
		array $command,
		int $exitCode,
		string $stdout = '',
		string $stderr = ''
	) :\RuntimeException {
		$message = $summary
			."\nCompose project: ".self::COMPOSE_PROJECT_NAME
			."\nExit code: ".$exitCode
			."\nCommand: ".$this->formatCommand( $command );
		if ( \trim( $stderr ) !== '' ) {
			$message .= "\nStderr: ".$this->trimDiagnosticBuffer( $stderr );
		}
		if ( \trim( $stdout ) !== '' ) {
			$message .= "\nStdout: ".$this->trimDiagnosticBuffer( $stdout );
		}

		return new \RuntimeException( $message );
	}

	/**
	 * @param string[] $command
	 */
	private function formatCommand( array $command ) :string {
		return \implode( ' ', \array_map(
			static fn( string $part ) :string => \preg_match( '/\s/', $part ) === 1 ? '"'.$part.'"' : $part,
			$command
		) );
	}

	/**
	 * @return array{url:string,title:string,wp_cli_service:string}
	 */
	private function siteDefinition( string $site ) :array {
		if ( $site === self::MASTER ) {
			return [
				'url'            => self::MASTER_INTERNAL_URL,
				'title'          => 'Shield Cross-Site Master',
				'wp_cli_service' => self::MASTER_WPCLI_SERVICE,
			];
		}
		if ( $site === self::SLAVE ) {
			return [
				'url'            => self::SLAVE_INTERNAL_URL,
				'title'          => 'Shield Cross-Site Slave',
				'wp_cli_service' => self::SLAVE_WPCLI_SERVICE,
			];
		}
		throw new \InvalidArgumentException( 'Unknown cross-site role: '.$site );
	}

	private function setupOutputHandler( bool $showSetupOutput ) :?callable {
		return $showSetupOutput ? null : static function () :void {};
	}

	private function stage( string $stage ) :void {
		$this->lastStage = $stage;
	}
}

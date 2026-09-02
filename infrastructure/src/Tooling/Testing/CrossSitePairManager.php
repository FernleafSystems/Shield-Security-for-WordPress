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
	private const MASTER_INTERNAL_URL = 'http://wordpress-master.shield-cross-site.example.com';
	private const SLAVE_INTERNAL_URL = 'http://wordpress-slave.shield-cross-site.example.com';
	private const MASTER_DB_NAME = 'shield_cross_site_master';
	private const SLAVE_DB_NAME = 'shield_cross_site_slave';
	private const MASTER_HOST_PORT = '8892';
	private const SLAVE_HOST_PORT = '8893';
	private const REUSABLE_DOCKER_RUN_ID = 'shield-plugin-cross-site-reusable';
	private const REUSABLE_DOCKER_EXPIRES_AT = '2037-12-31T23:59:59+00:00';
	private const HELPER_FILE = '/app/tests/Helpers/CrossSite/CrossSiteRuntime.php';
	private const PUBLIC_VERSION = '22.1.3';
	private const PLUGIN_SLUG = 'wp-simple-firewall';
	private const PLUGIN_FILE = 'wp-simple-firewall/icwp-wpsf.php';
	private const PUBLIC_IMPORT_FIXTURE = '/app/tests/fixtures/cross-site/public-22.1.3-import.json';
	private const PUBLIC_RUNTIME_FIXTURE = '/app/tests/fixtures/cross-site/public-22.1.3-runtime.php';
	private const PUBLIC_RUNTIME_TARGET = '/var/www/html/wp-content/mu-plugins/shield-cross-site-public-22.1.3-runtime.php';
	private const PUBLIC_RUNTIME_API_KEY = 'shield-cross-site-public-license-key';
	private const PUBLIC_RUNTIME_SITE_SECRET = '0123456789abcdef0123456789abcdef01234567';
	private const AUTOMATIC_CRON_BLOCKER_FIXTURE = '/app/tests/fixtures/cross-site/block-automatic-cron.php';
	private const AUTOMATIC_CRON_BLOCKER_TARGET = '/var/www/html/wp-content/mu-plugins/shield-cross-site-block-automatic-cron.php';
	private const UPDATE_PROVIDER_FIXTURE = '/app/tests/fixtures/upgrade-public/update-provider.php';
	private const UPDATE_CONFIG_FIXTURE = '/app/tests/fixtures/upgrade-public/write-update-config.php';
	private const UPDATE_PACKAGE_DIR = '/var/www/html/wp-content/uploads/shield-cross-site-upgrade';
	private const UPDATE_PACKAGE_FILE = self::UPDATE_PACKAGE_DIR.'/wp-simple-firewall-current.zip';
	private const STATUS_ACTIVE = 'active';
	private const QUEUE_IDLE = 'idle';
	private const QUEUE_WAITING_EXPORT = 'waiting_export';
	private const EXPORT_RESULT_SUCCESS = 'success';
	private const WP_CLI_INVALID_CRON_EVENT = 'Invalid cron event';

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalSiteRuntimeRefresher $runtimeRefresher;

	private SourceSetupCacheCoordinator $setupCacheCoordinator;

	private PublicUpgradePackageZipResolver $packageZipResolver;

	private string $lastStage = 'not started';

	/** @var array<string,mixed> */
	private array $lastDiagnostics = [];

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteRuntimeRefresher $runtimeRefresher = null,
		?SourceSetupCacheCoordinator $setupCacheCoordinator = null,
		?PublicUpgradePackageZipResolver $packageZipResolver = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->runtimeRefresher = $runtimeRefresher ?? new LocalSiteRuntimeRefresher( $this->processRunner );
		$this->setupCacheCoordinator = $setupCacheCoordinator ?? new SourceSetupCacheCoordinator();
		$this->packageZipResolver = $packageZipResolver ?? new PublicUpgradePackageZipResolver( $this->processRunner );
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
			$this->buildDatabaseUpCommand(),
			$envOverrides,
			$onOutput,
			$showDockerOutput
		);
		if ( $exitCode !== 0 ) {
			throw $this->composeFailureException(
				'Failed to start the cross-site Docker database service.',
				$this->buildDatabaseUpCommand(),
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

		if ( $mode === 'clean' ) {
			$this->provisionSites( $rootDir, $envOverrides, $onOutput, true );
		}
		else {
			$this->refreshCheckoutRuntimeWithEnvironment( $rootDir, $envOverrides, $onOutput );
		}
	}

	public function refreshCheckoutRuntime( string $rootDir, bool $showSetupOutput = false ) :void {
		$this->refreshCheckoutRuntimeWithEnvironment(
			$rootDir,
			$this->buildRuntimeEnvOverrides( $rootDir ),
			$this->setupOutputHandler( $showSetupOutput )
		);
	}

	public function refreshCheckoutRuntimeAfterPublicUpgrade( string $rootDir, bool $showSetupOutput = false ) :void {
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->stage( 'remove public '.$site.' runtime for checkout refresh' );
			$this->removePublicPluginForCheckoutRefresh( $rootDir, $site );
		}
		$this->refreshCheckoutRuntime( $rootDir, $showSetupOutput );
	}

	public function runPublicUpgradeScenario( string $rootDir, string $archiveWorkspace ) :void {
		$this->stage( 'build checkout package for public upgrade' );
		$artifacts = PublicUpgradeArtifacts::resolve( $rootDir, $archiveWorkspace );
		$artifacts->resetForRun();
		$metadata = $this->packageZipResolver->resolve( $rootDir, null, $artifacts );
		$this->assertUpgradePackageMetadata( $metadata );
		$this->lastDiagnostics[ 'public_upgrade_package' ] = [
			'path' => $metadata->zipPath(),
			'version' => $metadata->version(),
			'plugin_file' => $metadata->pluginFile(),
		];

		$this->stage( 'install public Shield on cross-site pair' );
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->installPublicPlugin( $rootDir, $site );
		}

		$this->stage( 'install cross-site automatic cron blocker fixture' );
		$this->installAutomaticCronBlockerFixture( $rootDir );

		try {
			$this->stage( 'install public 22.1.3 runtime fixture' );
			$this->installPublicRuntimeFixture( $rootDir );

			$this->stage( 'connect public slave to master' );
			foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
				$this->wpCapture( $rootDir, $site, [
					'shield',
					'pro-license',
					'--action=activate',
					'--api-key='.self::PUBLIC_RUNTIME_API_KEY,
					'--force',
				] );
			}
			$this->wpCapture( $rootDir, self::SLAVE, [
				'shield',
				'import',
				'--source='.self::MASTER_INTERNAL_URL,
				'--site-secret='.self::PUBLIC_RUNTIME_SITE_SECRET,
				'--slave=add',
				'--force',
			] );
			$this->lastDiagnostics[ 'public_connection_options' ] = $this->publicCliOptionsSnapshot( $rootDir, self::SLAVE );

			$this->stage( 'import public master fixture' );
			$this->wpCapture( $rootDir, self::MASTER, [
				'shield',
				'import',
				'--source='.self::PUBLIC_IMPORT_FIXTURE,
				'--force',
			] );

			$this->stage( 'set public master legacy sync value' );
			$this->setShieldOptionViaCli( $rootDir, self::MASTER, 'importexport_enable', 'Y' );
			$this->assertPublicCliOption( $rootDir, self::MASTER, 'importexport_enable', 'Y' );
			$this->lastDiagnostics[ 'public_master_legacy_value' ] = [
				'importexport_enable' => 'Y',
			];

			$this->stage( 'set distinct public slave options' );
			foreach ( [
				'display_plugin_badge' => 'disabled',
				'visitor_address_source' => 'AUTO_DETECT_IP',
				'enable_tracking' => 'N',
			] as $key => $value ) {
				$this->setShieldOptionViaCli( $rootDir, self::SLAVE, $key, $value );
				$this->assertPublicCliOption( $rootDir, self::SLAVE, $key, $value );
			}
			$this->lastDiagnostics[ 'public_pre_transfer_options' ] = [
				'display_plugin_badge' => 'disabled',
				'visitor_address_source' => 'AUTO_DETECT_IP',
				'enable_tracking' => 'N',
			];

			$this->stage( 'run public cross-site cron sync' );
			$this->runPublicCronSync( $rootDir );
			$this->assertPublicCliOption( $rootDir, self::SLAVE, 'display_plugin_badge', 'light' );
			$this->assertPublicCliOption( $rootDir, self::SLAVE, 'visitor_address_source', 'REMOTE_ADDR' );
			$this->assertPublicCliOption( $rootDir, self::SLAVE, 'enable_tracking', 'N' );
		}
		finally {
			$this->stage( 'remove public 22.1.3 runtime fixture' );
			$this->removePublicRuntimeFixture( $rootDir );
		}

		$this->stage( 'configure cross-site update provider' );
		$this->configureUpdateProvider( $rootDir, $metadata );

		$this->stage( 'update cross-site pair through WordPress' );
		$updates = [];
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$result = $this->runPluginUpdate( $rootDir, $site );
			$this->assertPluginUpdateResult( $result, $metadata );
			$this->assertInstalledPluginVersion( $rootDir, $site, $metadata->version(), 'native update' );
			$updates[ $site ] = $result;
		}
		$this->lastDiagnostics[ 'public_upgrade_results' ] = $updates;

		$this->stage( 'process migrated master queue' );
		$this->runMasterSitesQueueEvent( $rootDir );

		$this->stage( 'capture migrated master state' );
		$migrationBeforeSlaveImport = $this->runHelper( $rootDir, self::MASTER, 'migration-state' );
		$queueBeforeSlaveImport = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->lastDiagnostics[ 'master_queue_after_notify_dispatch' ] = $queueBeforeSlaveImport;
		$this->assertPostNotifyDispatchQueueState( $queueBeforeSlaveImport );
		$this->assertPublicMigrationState( $migrationBeforeSlaveImport );

		$this->stage( 'reset current slave values before scheduled import' );
		foreach ( [
			'display_plugin_badge' => 'disabled',
			'visitor_address_source' => 'AUTO_DETECT_IP',
			'enable_tracking' => 'N',
		] as $key => $value ) {
			$this->setShieldOptionViaCli( $rootDir, self::SLAVE, $key, $value );
			$this->assertPublicCliOption( $rootDir, self::SLAVE, $key, $value );
		}
		$slaveBeforeImport = $this->publicSlaveSnapshot( $rootDir );
		$this->assertPublicSlaveOptions( $slaveBeforeImport, 'disabled', 'AUTO_DETECT_IP', 'N', 'before current import' );

		$this->stage( 'run scheduled migrated slave import' );
		$this->runScheduledCronEvent(
			$rootDir,
			self::SLAVE,
			(array)$slaveBeforeImport[ 'cron' ],
			'import_hook',
			'import_scheduled'
		);
		$queueAfterImport = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->assertPostExportQueueState( $queueAfterImport );
		$this->assertPublicQueueTransition( $queueBeforeSlaveImport, $queueAfterImport );

		$this->stage( 'assert scheduled import causality' );
		$migrationAfterSlaveImport = $this->runHelper( $rootDir, self::MASTER, 'migration-state' );
		$slaveAfterImport = $this->publicSlaveSnapshot( $rootDir );
		$this->assertPublicSlaveOptions( $slaveAfterImport, 'light', 'REMOTE_ADDR', 'N', 'after current import' );
		$this->assertMigrationStateUnchanged( $migrationBeforeSlaveImport, $migrationAfterSlaveImport );
		$this->lastDiagnostics[ 'public_migration' ] = [
			'before_slave_import' => $migrationBeforeSlaveImport,
			'after_slave_import' => $migrationAfterSlaveImport,
			'queue_before_slave_import' => $queueBeforeSlaveImport,
			'queue_after_slave_import' => $queueAfterImport,
			'slave_before_import' => $slaveBeforeImport,
			'slave_after_import' => $slaveAfterImport,
		];
		$this->assertExportsMatch( $rootDir, (array)( $migrationBeforeSlaveImport[ 'root_xfer_excluded' ] ?? [] ) );
	}

	public function runImportExportScenario( string $rootDir ) :void {
		$this->stage( 'setup cross-site runtime state' );
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
			 || !\in_array( self::SLAVE_INTERNAL_URL, $masterState[ 'sync_site_urls' ] ?? [], true ) ) {
			throw new \RuntimeException( 'Master sync-sites registry does not contain the slave internal URL.' );
		}
		$this->assertRegistryContainsSlave( $masterState, 'after slave connection' );
		if ( !\is_array( $slaveState ) || ( $slaveState[ 'master_url' ] ?? '' ) !== self::MASTER_INTERNAL_URL ) {
			throw new \RuntimeException( 'Slave master URL was not set to the master internal URL.' );
		}

		$this->stage( 'apply master option corpus' );
		$corpus = $this->runHelper( $rootDir, self::MASTER, 'apply-corpus' );
		$this->lastDiagnostics[ 'corpus' ] = $this->summariseCorpusDiagnostics( $corpus );

		$this->stage( 'trigger master option-save notification' );
		$this->lastDiagnostics[ 'legacy_notify' ] = $this->runHelper( $rootDir, self::MASTER, 'run-notify-hook' );

		$this->stage( 'process master DB-backed site queue' );
		$this->processMasterSitesQueue( $rootDir );

		$this->stage( 'wait for slave import completion' );
		$queueAfterImport = $this->waitForSlaveImportCompletion( $rootDir );
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

	private function assertUpgradePackageMetadata( PublicUpgradePackageZipMetadata $metadata ) :void {
		if ( \version_compare( $metadata->version(), self::PUBLIC_VERSION, '<=' ) ) {
			throw new \RuntimeException(
				'Checkout package version '.$metadata->version().' must be greater than public version '.self::PUBLIC_VERSION.'.'
			);
		}
		if ( $metadata->pluginFile() !== self::PLUGIN_FILE ) {
			throw new \RuntimeException( 'Checkout package did not contain the expected Shield plugin artifact.' );
		}
	}

	private function assertInstalledPluginVersion(
		string $rootDir,
		string $site,
		string $expectedVersion,
		string $context
	) :void {
		$captured = $this->wpCapture( $rootDir, $site, [
			'plugin',
			'get',
			self::PLUGIN_SLUG,
			'--field=version',
		] );
		$actualVersion = \trim( $captured[ 'stdout' ] );
		if ( $actualVersion !== $expectedVersion ) {
			throw new \RuntimeException(
				'Shield version on '.$site.' after '.$context.' was '.$actualVersion.', expected '.$expectedVersion.'.'
			);
		}
	}

	private function installPublicPlugin( string $rootDir, string $site ) :void {
		$this->wpCapture( $rootDir, $site, [
			'plugin',
			'install',
			self::PLUGIN_SLUG,
			'--version='.self::PUBLIC_VERSION,
			'--activate',
			'--force',
		] );
		$this->assertInstalledPluginVersion( $rootDir, $site, self::PUBLIC_VERSION, 'public install' );
	}

	private function removePublicPluginForCheckoutRefresh( string $rootDir, string $site ) :void {
		$this->wpCapture( $rootDir, $site, [ 'plugin', 'deactivate', self::PLUGIN_SLUG ] );
		$this->wpCapture( $rootDir, $site, [ 'plugin', 'delete', self::PLUGIN_SLUG ] );
	}

	private function installAutomaticCronBlockerFixture( string $rootDir ) :void {
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->runSiteShell(
				$rootDir,
				$site,
				'mkdir -p /var/www/html/wp-content/mu-plugins'
				.' && cp '.self::AUTOMATIC_CRON_BLOCKER_FIXTURE.' '.self::AUTOMATIC_CRON_BLOCKER_TARGET
			);
		}
	}

	private function installPublicRuntimeFixture( string $rootDir ) :void {
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->runSiteShell(
				$rootDir,
				$site,
				'mkdir -p /var/www/html/wp-content/mu-plugins'
				.' && cp '.self::PUBLIC_RUNTIME_FIXTURE.' '.self::PUBLIC_RUNTIME_TARGET
			);
		}
	}

	private function removePublicRuntimeFixture( string $rootDir ) :void {
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->runSiteShell( $rootDir, $site, 'rm -f '.self::PUBLIC_RUNTIME_TARGET );
		}
	}

	private function configureUpdateProvider(
		string $rootDir,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		$relativeZip = \str_replace( '\\', '/', Path::makeRelative( $metadata->zipPath(), $rootDir ) );
		if ( $relativeZip === '' || \strpos( $relativeZip, '../' ) === 0 ) {
			throw new \RuntimeException( 'Cross-site update package must be inside the repository workspace.' );
		}
		$sha256 = \hash_file( 'sha256', $metadata->zipPath() );
		if ( !\is_string( $sha256 ) || !\preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			throw new \RuntimeException( 'Could not calculate the cross-site update package identity.' );
		}
		$packageUrl = self::MASTER_INTERNAL_URL.'/wp-content/uploads/shield-cross-site-upgrade/wp-simple-firewall-current.zip';

		$publishedOutput = $this->runSiteShell(
			$rootDir,
			self::MASTER,
			'mkdir -p '.self::UPDATE_PACKAGE_DIR.' /var/www/html/wp-content/mu-plugins'
			.' && cp '.\escapeshellarg( '/app/'.$relativeZip ).' '.self::UPDATE_PACKAGE_FILE
			.' && cp '.self::UPDATE_PROVIDER_FIXTURE.' /var/www/html/wp-content/mu-plugins/shield-upgrade-test-update-provider.php'
			.' && sha256sum '.self::UPDATE_PACKAGE_FILE
		);
		if ( !\preg_match( '/^([a-f0-9]{64})\s+/mi', $publishedOutput, $matches )
			 || \strtolower( (string)$matches[ 1 ] ) !== $sha256 ) {
			throw new \RuntimeException( 'Published cross-site update package identity did not match the checkout archive.' );
		}
		$this->runSiteShell(
			$rootDir,
			self::SLAVE,
			'mkdir -p /var/www/html/wp-content/mu-plugins'
			.' && cp '.self::UPDATE_PROVIDER_FIXTURE.' /var/www/html/wp-content/mu-plugins/shield-upgrade-test-update-provider.php'
		);

		$config = \base64_encode( \json_encode( [
			'plugin'      => self::PLUGIN_FILE,
			'slug'        => self::PLUGIN_SLUG,
			'id'          => self::PLUGIN_SLUG,
			'new_version' => $metadata->version(),
			'package'     => $packageUrl,
			'package_sha256' => $sha256,
			'url'         => 'https://wordpress.org/plugins/'.self::PLUGIN_SLUG.'/',
		], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) );
		$configuredIdentity = [];
		foreach ( [ self::MASTER, self::SLAVE ] as $site ) {
			$this->wpCapture( $rootDir, $site, [ 'eval-file', self::UPDATE_CONFIG_FIXTURE, $config ] );
			$configuredIdentity[ $site ] = [
				'package_url' => $packageUrl,
				'sha256' => $sha256,
			];
		}
		if ( \count( $configuredIdentity ) !== 2
			 || $configuredIdentity[ self::MASTER ] !== $configuredIdentity[ self::SLAVE ] ) {
			throw new \RuntimeException( 'Cross-site pair was not configured with one shared update artifact identity.' );
		}
		$this->lastDiagnostics[ 'public_upgrade_artifact_identity' ] = [
			'package_url' => $packageUrl,
			'sha256' => $sha256,
			'configured_sites' => $configuredIdentity,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function runPluginUpdate( string $rootDir, string $site ) :array {
		$captured = $this->wpCapture( $rootDir, $site, [
			'plugin',
			'update',
			self::PLUGIN_SLUG,
			'--format=json',
		] );
		$decoded = \json_decode( \trim( $captured[ 'stdout' ] ), true );
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException( 'Plugin update on '.$site.' did not return valid JSON.' );
		}
		if ( $this->isList( $decoded ) ) {
			$decoded = $decoded[ 0 ] ?? null;
		}
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException( 'Plugin update on '.$site.' did not return a result row.' );
		}
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $result
	 */
	private function assertPluginUpdateResult(
		array $result,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		if ( (string)( $result[ 'status' ] ?? '' ) !== 'Updated'
			 || (string)( $result[ 'old_version' ] ?? '' ) !== self::PUBLIC_VERSION
			 || (string)( $result[ 'new_version' ] ?? '' ) !== $metadata->version() ) {
			throw new \RuntimeException( 'Native Shield plugin update result did not match the public-to-checkout contract.' );
		}
	}

	/**
	 * @param array<string,mixed> $migration
	 */
	private function assertPublicMigrationState( array $migration ) :void {
		if ( empty( $migration[ 'migration_completed' ] ) ) {
			throw new \RuntimeException( 'Public master legacy-site migration was not recorded as complete.' );
		}
		if ( \array_values( (array)( $migration[ 'root_xfer_excluded' ] ?? [] ) ) !== [ 'enable_tracking' ] ) {
			throw new \RuntimeException( 'Public master root transfer exclusions did not survive the upgrade.' );
		}
		$profileableKeys = \array_values( \array_map( 'strval', (array)( $migration[ 'profileable_keys' ] ?? [] ) ) );
		foreach ( [ 'display_plugin_badge', 'visitor_address_source', 'enable_tracking' ] as $key ) {
			if ( !\in_array( $key, $profileableKeys, true ) ) {
				throw new \RuntimeException( 'Public master profileable option catalog did not contain '.$key.'.' );
			}
		}

		$defaultIDs = \array_values( \array_map( '\intval', (array)( $migration[ 'default_profile_ids' ] ?? [] ) ) );
		if ( \count( $defaultIDs ) !== 1 || $defaultIDs[ 0 ] <= 0 ) {
			throw new \RuntimeException( 'Public master migration did not create exactly one default profile.' );
		}
		$profiles = (array)( $migration[ 'profiles' ] ?? [] );
		$defaultProfile = null;
		foreach ( $profiles as $profile ) {
			if ( \is_array( $profile ) && (int)( $profile[ 'id' ] ?? 0 ) === $defaultIDs[ 0 ] ) {
				$defaultProfile = $profile;
				break;
			}
		}
		if ( \count( $profiles ) !== 1
			 || !\is_array( $defaultProfile )
			 || empty( $defaultProfile[ 'is_default' ] )
			 || (string)( $defaultProfile[ 'slug' ] ?? '' ) !== 'default'
			 || (string)( $defaultProfile[ 'label' ] ?? '' ) === ''
			 || !empty( $defaultProfile[ 'non_profileable_option_keys' ] ) ) {
			throw new \RuntimeException( 'Public master default profile migration invariants were not satisfied.' );
		}
		$options = (array)( $defaultProfile[ 'options' ] ?? [] );
		\ksort( $options );
		$profileableOptions = (array)( $migration[ 'profileable_options' ] ?? [] );
		\ksort( $profileableOptions );
		if ( (string)( $options[ 'display_plugin_badge' ] ?? '' ) !== 'light'
			 || (string)( $options[ 'visitor_address_source' ] ?? '' ) !== 'REMOTE_ADDR'
			 || (string)( $options[ 'enable_tracking' ] ?? '' ) !== 'Y'
			 || $options !== $profileableOptions
			 || \array_values( (array)( $defaultProfile[ 'excluded' ] ?? [] ) ) !== [ 'enable_tracking' ] ) {
			throw new \RuntimeException( 'Public master default profile did not preserve imported values and exclusions.' );
		}

		$registry = (array)( $migration[ 'active_registry' ] ?? [] );
		$row = null;
		foreach ( $registry as $candidate ) {
			if ( \is_array( $candidate ) && (string)( $candidate[ 'url' ] ?? '' ) === self::SLAVE_INTERNAL_URL ) {
				$row = $candidate;
				break;
			}
		}
		if ( \count( $registry ) !== 1
			 || !\is_array( $row )
			 || (string)( $row[ 'import_id' ] ?? '' ) === ''
			 || (string)( $row[ 'source' ] ?? '' ) !== 'legacy_option'
			 || (int)( $row[ 'profile_ref' ] ?? 0 ) !== $defaultIDs[ 0 ]
			 || empty( $row[ 'profile_resolves_to_default' ] ) ) {
			throw new \RuntimeException( 'Public master active slave registry did not migrate to the default profile.' );
		}
		if ( (string)( $migration[ 'master_sync_enabled' ] ?? '' ) !== 'Y'
			 || \array_values( (array)( $migration[ 'master_sync_urls' ] ?? [] ) ) !== [ self::SLAVE_INTERNAL_URL ] ) {
			throw new \RuntimeException( 'Public master sync state did not preserve the connected slave.' );
		}
	}

	private function runPublicCronSync( string $rootDir ) :void {
		$this->wpCapture( $rootDir, self::MASTER, [
			'cron',
			'event',
			'schedule',
			'icwp-wpsf-importexport_notify',
			'now',
		] );
		$notifyHook = $this->waitForScheduledCronHook( $rootDir, self::MASTER, 'importexport_notify' );
		$this->wpCapture( $rootDir, self::MASTER, [ 'cron', 'event', 'run', $notifyHook ] );

		$importHook = $this->waitForScheduledCronHook( $rootDir, self::SLAVE, 'importexport_updatenotified' );
		$this->wpCapture( $rootDir, self::SLAVE, [ 'cron', 'event', 'run', $importHook ] );
		$this->lastDiagnostics[ 'public_cron_sync' ] = [
			'notify_hook' => $notifyHook,
			'import_hook' => $importHook,
		];
	}

	private function waitForScheduledCronHook( string $rootDir, string $site, string $suffix ) :string {
		$startedAt = \time();
		do {
			$captured = $this->wpCapture( $rootDir, $site, [
				'cron',
				'event',
				'list',
				'--fields=hook',
				'--format=json',
			] );
			$events = \json_decode( \trim( $captured[ 'stdout' ] ), true );
			$hooks = [];
			foreach ( \is_array( $events ) ? $events : [] as $event ) {
				$hook = \is_array( $event ) ? (string)( $event[ 'hook' ] ?? '' ) : '';
				if ( $hook !== '' && \substr( $hook, -\strlen( $suffix ) ) === $suffix ) {
					$hooks[] = $hook;
				}
			}
			$hooks = \array_values( \array_unique( $hooks ) );
			if ( \count( $hooks ) === 1 ) {
				return $hooks[ 0 ];
			}
			if ( \count( $hooks ) > 1 ) {
				throw new \RuntimeException( 'Discovered multiple scheduled public cron hooks ending in '.$suffix.'.' );
			}
			\sleep( 1 );
		} while ( \time() - $startedAt < 30 );

		throw new \RuntimeException( 'No scheduled public cron hook ending in '.$suffix.' became available.' );
	}

	private function setShieldOptionViaCli( string $rootDir, string $site, string $key, string $value ) :void {
		$this->wpCapture( $rootDir, $site, [
			'shield',
			'opt-set',
			'--key='.$key,
			'--value='.$value,
		] );
	}

	private function assertPublicCliOption( string $rootDir, string $site, string $key, string $expected ) :void {
		if ( $this->readPublicCliOption( $rootDir, $site, $key ) !== $expected ) {
			throw new \RuntimeException( 'Public Shield option '.$key.' on '.$site.' did not equal '.$expected.'.' );
		}
	}

	/**
	 * @return array<string,string>
	 */
	private function publicCliOptionsSnapshot( string $rootDir, string $site ) :array {
		$snapshot = [];
		foreach ( [ 'display_plugin_badge', 'visitor_address_source', 'enable_tracking' ] as $key ) {
			$snapshot[ $key ] = $this->readPublicCliOption( $rootDir, $site, $key );
		}
		return $snapshot;
	}

	private function readPublicCliOption( string $rootDir, string $site, string $key ) :string {
		$captured = $this->wpCapture( $rootDir, $site, [ 'shield', 'opt-get', '--key='.$key ] );
		if ( !\preg_match( '/(?:^|\R)Current value:\s*(.*?)\s*$/m', \trim( $captured[ 'stdout' ] ), $matches ) ) {
			throw new \RuntimeException( 'Public Shield option '.$key.' on '.$site.' did not return a readable value.' );
		}
		return (string)$matches[ 1 ];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function publicSlaveSnapshot( string $rootDir ) :array {
		$state = $this->runHelper( $rootDir, self::SLAVE, 'state' );
		$cron = $this->runHelper( $rootDir, self::SLAVE, 'cron-state' );
		$snapshot = [
			'state' => $state,
			'cron' => $cron,
			'options' => $this->publicCliOptionsSnapshot( $rootDir, self::SLAVE ),
		];

		if ( (string)( $state[ 'master_url' ] ?? '' ) !== self::MASTER_INTERNAL_URL
			 || (string)( $cron[ 'master_url' ] ?? '' ) !== self::MASTER_INTERNAL_URL
			 || (string)( $cron[ 'import_id' ] ?? '' ) === '' ) {
			throw new \RuntimeException( 'Public slave did not preserve its master URL and import ID through the upgrade.' );
		}
		return $snapshot;
	}

	/**
	 * @param array<string,mixed> $snapshot
	 */
	private function assertPublicSlaveOptions(
		array $snapshot,
		string $expectedBadge,
		string $expectedAddressSource,
		string $expectedTracking,
		string $context
	) :void {
		$options = (array)( $snapshot[ 'options' ] ?? [] );
		if ( (string)( $options[ 'display_plugin_badge' ] ?? '' ) !== $expectedBadge
			 || (string)( $options[ 'visitor_address_source' ] ?? '' ) !== $expectedAddressSource
			 || (string)( $options[ 'enable_tracking' ] ?? '' ) !== $expectedTracking ) {
			throw new \RuntimeException( 'Public slave option causality failed '.$context.'.' );
		}
	}

	private function assertMigrationStateUnchanged( array $before, array $after ) :void {
		$this->sortRecursive( $before );
		$this->sortRecursive( $after );
		if ( $before !== $after ) {
			throw new \RuntimeException( 'Scheduled slave import mutated the read-only master migration state.' );
		}
	}

	private function assertPublicQueueTransition( array $before, array $after ) :void {
		$beforeRow = $this->findRegistryRow( (array)( $before[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		$afterRow = $this->findRegistryRow( (array)( $after[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $beforeRow ) || !\is_array( $afterRow ) ) {
			throw new \RuntimeException( 'Public master queue transition lost the named slave row.' );
		}
		$beforeMeta = $beforeRow[ 'meta' ] ?? null;
		$afterMeta = $afterRow[ 'meta' ] ?? null;
		if ( !\is_array( $beforeMeta ) || !\is_array( $afterMeta ) ) {
			throw new \RuntimeException( 'Public master queue transition did not preserve registry metadata.' );
		}
		if ( \array_key_exists( 'export_served_at', $beforeMeta )
			&& ( !\is_int( $beforeMeta[ 'export_served_at' ] ) || $beforeMeta[ 'export_served_at' ] !== 0 ) ) {
			throw new \RuntimeException( 'Public master queue transition began with an invalid export-served marker.' );
		}
		if ( !isset( $afterMeta[ 'export_served_at' ] )
			 || !\is_int( $afterMeta[ 'export_served_at' ] )
			 || $afterMeta[ 'export_served_at' ] <= 0 ) {
			throw new \RuntimeException( 'Public master queue transition did not record a successful export-served marker.' );
		}
		unset( $beforeMeta[ 'export_served_at' ], $afterMeta[ 'export_served_at' ] );
		$this->sortRecursive( $beforeMeta );
		$this->sortRecursive( $afterMeta );
		$beforeRow[ 'meta' ] = $beforeMeta;
		$afterRow[ 'meta' ] = $afterMeta;

		$allowed = [
			'queue_status',
			'next_ping_at',
			'last_ping_attempt_at',
			'last_ping_success_at',
			'last_ping_http_code',
			'last_ping_error',
			'last_export_request_at',
			'last_export_success_at',
			'last_export_result_code',
			'last_export_error',
			'consecutive_failures',
			'expected_export_by',
			'lock_until',
			'picked_at',
			'updated_at',
		];
		$beforeStable = \array_diff_key( $beforeRow, \array_flip( $allowed ) );
		$afterStable = \array_diff_key( $afterRow, \array_flip( $allowed ) );
		$this->sortRecursive( $beforeStable );
		$this->sortRecursive( $afterStable );
		if ( $beforeStable !== $afterStable ) {
			throw new \RuntimeException( 'Public master queue transition changed fields outside the approved queue lifecycle set.' );
		}
	}

	/**
	 * @param array<mixed> $value
	 */
	private function isList( array $value ) :bool {
		return $value === [] || \array_keys( $value ) === \range( 0, \count( $value ) - 1 );
	}

	/**
	 * @param array<string,mixed> $cronState
	 */
	private function runScheduledCronEvent(
		string $rootDir,
		string $site,
		array $cronState,
		string $hookKey,
		string $scheduledKey
	) :void {
		$hook = (string)( $cronState[ $hookKey ] ?? '' );
		if ( $hook === '' || empty( $cronState[ $scheduledKey ] ) ) {
			throw new \RuntimeException( 'Expected scheduled cross-site cron event was not discoverable: '.$hookKey );
		}
		$this->wpCapture( $rootDir, $site, [ 'cron', 'event', 'run', $hook ] );
	}

	private function processMasterSitesQueue( string $rootDir ) :void {
		$this->runMasterSitesQueueEvent( $rootDir );

		$queueAfter = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
		$this->lastDiagnostics[ 'master_queue_after_notify_dispatch' ] = $queueAfter;
		$this->assertPostNotifyDispatchQueueState( $queueAfter );
	}

	private function runMasterSitesQueueEvent( string $rootDir ) :void {
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
	}

	/**
	 * @return array<string,mixed>
	 */
	private function waitForSlaveImportCompletion( string $rootDir ) :array {
		$startedAt = \time();
		$directImportAttempted = false;
		do {
			$lastQueueState = $this->runHelper( $rootDir, self::MASTER, 'queue-state' );
			$this->lastDiagnostics[ 'master_queue_after_import' ] = $lastQueueState;
			if ( $this->isPostExportQueueState( $lastQueueState ) ) {
				return $lastQueueState;
			}

			$slaveCron = $this->runHelper( $rootDir, self::SLAVE, 'cron-state' );
			$this->lastDiagnostics[ 'slave_cron' ] = $slaveCron;
			$importHook = (string)( $slaveCron[ 'import_hook' ] ?? '' );
			if ( !empty( $slaveCron[ 'import_scheduled' ] ) && $importHook !== '' ) {
				$captured = $this->wpCapture( $rootDir, self::SLAVE, [ 'cron', 'event', 'run', $importHook ], false );
				if ( $captured[ 'exit_code' ] !== 0 && !$this->isInvalidCronEventFailure( $captured ) ) {
					throw $this->wpCliFailureException( self::SLAVE, $captured );
				}
				continue;
			}

			if ( $this->isWaitingForSlaveExport( $lastQueueState ) ) {
				if ( $this->slaveNotificationAccepted( $slaveCron ) ) {
					if ( !$directImportAttempted ) {
						$directImportAttempted = true;
						try {
							$this->lastDiagnostics[ 'slave_direct_import' ] = $this->runHelper(
								$rootDir,
								self::SLAVE,
								'run-import-from-master'
							);
						}
						catch ( \RuntimeException $exception ) {
							throw new \RuntimeException(
								'Slave direct import from master failed: '.$exception->getMessage(),
								0,
								$exception
							);
						}
						continue;
					}
				}
				else {
					throw new \RuntimeException(
						'Slave did not accept the master import notification; no import event or notify cooldown was visible.'
					);
				}
			}

			\sleep( 1 );
		} while ( \time() - $startedAt < 30 );

		throw new \RuntimeException( 'Slave import did not complete after master notification.' );
	}

	private function assertRegistryContainsSlave( array $state, string $context ) :void {
		$row = $this->findRegistryRow( (array)( $state[ 'registry' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) || ( $row[ 'status' ] ?? '' ) !== self::STATUS_ACTIVE ) {
			throw new \RuntimeException( 'Master registry does not contain the active slave URL '.$context.'.' );
		}
	}

	private function assertPostNotifyDispatchQueueState( array $queueState ) :void {
		$postExportFailure = $this->postExportQueueStateFailure( $queueState );
		if ( $postExportFailure === null ) {
			return;
		}

		$row = $this->findRegistryRow( (array)( $queueState[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue lost the slave registry row after notify dispatch.' );
		}
		if ( ( $row[ 'queue_status' ] ?? '' ) === self::QUEUE_IDLE ) {
			throw new \RuntimeException( $postExportFailure );
		}
		if ( ( $row[ 'queue_status' ] ?? '' ) !== self::QUEUE_WAITING_EXPORT ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not wait for slave export after notify dispatch.' );
		}
		if ( (int)( $row[ 'last_ping_success_at' ] ?? 0 ) <= 0 ) {
			throw new \RuntimeException( 'Master DB-backed site queue did not record notify dispatch.' );
		}
		if ( (int)( $row[ 'last_export_success_at' ] ?? 0 ) > (int)( $row[ 'last_ping_success_at' ] ?? 0 ) ) {
			throw new \RuntimeException( 'Master DB-backed site queue counted notify dispatch as export sync success.' );
		}
	}

	private function assertPostExportQueueState( array $queueState ) :void {
		$failure = $this->postExportQueueStateFailure( $queueState );
		if ( $failure !== null ) {
			throw new \RuntimeException( $failure );
		}
	}

	private function isPostExportQueueState( array $queueState ) :bool {
		return $this->postExportQueueStateFailure( $queueState ) === null;
	}

	private function isWaitingForSlaveExport( array $queueState ) :bool {
		$row = $this->findRegistryRow( (array)( $queueState[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		return \is_array( $row ) && ( $row[ 'queue_status' ] ?? '' ) === self::QUEUE_WAITING_EXPORT;
	}

	private function slaveNotificationAccepted( array $slaveCron ) :bool {
		return !empty( $slaveCron[ 'notify_cooldown_active' ] );
	}

	private function postExportQueueStateFailure( array $queueState ) :?string {
		$row = $this->findRegistryRow( (array)( $queueState[ 'rows' ] ?? [] ), self::SLAVE_INTERNAL_URL );
		if ( !\is_array( $row ) ) {
			return 'Master DB-backed site queue lost the slave registry row after slave import.';
		}
		if ( ( $row[ 'queue_status' ] ?? '' ) !== self::QUEUE_IDLE ) {
			return 'Master DB-backed site queue did not return the slave row to idle after export.';
		}
		if ( (int)( $row[ 'last_export_request_at' ] ?? 0 ) <= 0
			 || (int)( $row[ 'last_export_success_at' ] ?? 0 ) <= 0 ) {
			return 'Master DB-backed site queue did not record export request and success.';
		}
		if ( (int)( $row[ 'last_ping_success_at' ] ?? 0 ) <= 0 ) {
			return 'Master DB-backed site queue did not record notify dispatch before export.';
		}
		$pingHttpCode = (int)( $row[ 'last_ping_http_code' ] ?? 0 );
		if ( $pingHttpCode < 200 || $pingHttpCode >= 300 || (string)( $row[ 'last_ping_error' ] ?? '' ) !== '' ) {
			return 'Master DB-backed site queue did not retain a successful notify response.';
		}
		if ( (int)( $row[ 'last_export_success_at' ] ?? 0 ) <= (int)( $row[ 'last_ping_success_at' ] ?? 0 ) ) {
			return 'Master DB-backed site queue did not record a new export success after notify dispatch.';
		}
		if ( ( $row[ 'last_export_result_code' ] ?? '' ) !== self::EXPORT_RESULT_SUCCESS ) {
			return 'Master DB-backed site queue did not record export success result code.';
		}
		if ( (string)( $row[ 'last_export_error' ] ?? '' ) !== ''
			 || (int)( $row[ 'consecutive_failures' ] ?? -1 ) !== 0 ) {
			return 'Master DB-backed site queue retained failure evidence after export success.';
		}
		if ( (int)( $row[ 'next_ping_at' ] ?? 0 ) <= 0
			 || (int)( $row[ 'expected_export_by' ] ?? -1 ) !== 0
			 || (int)( $row[ 'lock_until' ] ?? -1 ) !== 0
			 || (int)( $row[ 'picked_at' ] ?? -1 ) !== 0
			 || (int)( $row[ 'updated_at' ] ?? 0 ) <= 0 ) {
			return 'Master DB-backed site queue did not settle its lifecycle fields after export success.';
		}
		return null;
	}

	/**
	 * @param array{stdout:string,stderr:string,exit_code:int} $captured
	 */
	private function isInvalidCronEventFailure( array $captured ) :bool {
		return \str_contains( $captured[ 'stderr' ].$captured[ 'stdout' ], self::WP_CLI_INVALID_CRON_EVENT );
	}

	private function findRegistryRow( array $rows, string $url ) :?array {
		foreach ( $rows as $row ) {
			if ( \is_array( $row ) && ( $row[ 'url' ] ?? '' ) === $url ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param string[] $additionalExclusions
	 */
	private function assertExportsMatch( string $rootDir, array $additionalExclusions = [] ) :void {
		$masterExport = $this->runHelper( $rootDir, self::MASTER, 'export-options' );
		$slaveExport = $this->runHelper( $rootDir, self::SLAVE, 'export-options' );
		$exceptions = $this->exportComparisonExclusions( $masterExport, $slaveExport, $additionalExclusions );

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
	private function exportComparisonExclusions( array $masterExport, array $slaveExport, array $additionalExclusions = [] ) :array {
		return \array_values( \array_unique( \array_merge(
			(array)( $masterExport[ 'local_state_exceptions' ] ?? [] ),
			(array)( $slaveExport[ 'local_state_exceptions' ] ?? [] ),
			(array)( $masterExport[ 'runtime_invariant_keys' ] ?? [] ),
			(array)( $slaveExport[ 'runtime_invariant_keys' ] ?? [] ),
			$additionalExclusions
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
			$this->buildMysqlSqlCommand( $this->buildResetDatabasesSql() )
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
		$pingCommand = $this->buildMysqlPingCommand();
		$selectOneCommand = $this->buildMysqlSelectOneCommand();
		$lastCommand = $pingCommand;
		$lastProcess = null;
		$lastPhase = 'ping';
		$startedAt = \time();
		do {
			$pingProcess = $this->processRunner->run(
				$pingCommand,
				$rootDir,
				$onOutput,
				$envOverrides
			);
			if ( ( $pingProcess->getExitCode() ?? 1 ) !== 0 ) {
				$lastCommand = $pingCommand;
				$lastProcess = $pingProcess;
				$lastPhase = 'ping';
				\usleep( 500000 );
				continue;
			}

			$selectOneProcess = $this->processRunner->run(
				$selectOneCommand,
				$rootDir,
				$onOutput,
				$envOverrides
			);
			if ( ( $selectOneProcess->getExitCode() ?? 1 ) === 0 ) {
				return;
			}

			$lastCommand = $selectOneCommand;
			$lastProcess = $selectOneProcess;
			$lastPhase = 'sql';
			\usleep( 500000 );
		} while ( \time() - $startedAt < 60 );

		if ( !$lastProcess instanceof Process ) {
			throw new \RuntimeException( 'Cross-site MySQL readiness check did not run.' );
		}

		throw $this->commandFailureException(
			$lastPhase === 'sql'
				? 'Cross-site MySQL accepted ping but did not pass SELECT 1 within 60 seconds.'
				: 'Cross-site MySQL did not become ready within 60 seconds.',
			$lastCommand,
			$lastProcess->getExitCode() ?? 1,
			$lastProcess->getOutput(),
			$lastProcess->getErrorOutput()
		);
	}

	/**
	 * @return string[]
	 */
	private function buildDatabaseUpCommand() :array {
		return [ 'up', '-d', '--wait', '--wait-timeout', '60', self::DB_SERVICE_NAME ];
	}

	/**
	 * @return string[]
	 */
	private function buildMysqlPingCommand() :array {
		return \array_merge(
			$this->buildComposeCommandForExecution( [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			[ 'mysqladmin', 'ping', '--protocol=tcp', '-h', '127.0.0.1', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '--silent' ]
		);
	}

	/**
	 * @return string[]
	 */
	private function buildMysqlSelectOneCommand() :array {
		return \array_merge(
			$this->buildComposeCommandForExecution( [ 'exec', '-T', self::DB_SERVICE_NAME ] ),
			$this->buildMysqlSqlCommand( 'SELECT 1' )
		);
	}

	/**
	 * @return string[]
	 */
	private function buildMysqlSqlCommand( string $sql ) :array {
		return [ 'mysql', '--protocol=tcp', '-h', '127.0.0.1', '-uroot', '-p'.self::DB_ROOT_PASSWORD, '-e', $sql ];
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

	private function refreshCheckoutRuntimeWithEnvironment(
		string $rootDir,
		array $envOverrides,
		?callable $onOutput
	) :void {
		$this->stage( 'refresh master runtime' );
		$this->refreshRuntime( $rootDir, self::MASTER_WORDPRESS_SERVICE, $envOverrides, $onOutput );
		$this->stage( 'refresh slave runtime' );
		$this->refreshRuntime( $rootDir, self::SLAVE_WORDPRESS_SERVICE, $envOverrides, $onOutput );
		$this->provisionSites( $rootDir, $envOverrides, $onOutput, false );
	}

	private function provisionSites(
		string $rootDir,
		array $envOverrides,
		?callable $onOutput,
		bool $coreOnly
	) :void {
		$this->stage( $coreOnly ? 'provision master site core only' : 'provision master site' );
		$this->runProvision( $rootDir, self::MASTER, $envOverrides, $onOutput, $coreOnly );
		$this->stage( $coreOnly ? 'provision slave site core only' : 'provision slave site' );
		$this->runProvision( $rootDir, self::SLAVE, $envOverrides, $onOutput, $coreOnly );
		$this->stage( 'wait for master internal HTTP' );
		$this->waitForInternalHttpReady( $rootDir, self::SLAVE, self::MASTER_INTERNAL_URL.'/wp-login.php' );
		$this->stage( 'wait for slave internal HTTP' );
		$this->waitForInternalHttpReady( $rootDir, self::MASTER, self::SLAVE_INTERNAL_URL.'/wp-login.php' );
	}

	private function runProvision(
		string $rootDir,
		string $site,
		array $envOverrides,
		?callable $onOutput,
		bool $coreOnly
	) :void {
		$command = $this->buildProvisionCommand( $site, $coreOnly );
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

	private function runSiteShell( string $rootDir, string $site, string $script ) :string {
		$definition = $this->siteDefinition( $site );
		$command = $this->buildComposeCommandForExecution( [
			'run',
			'--rm',
			'-T',
			'--user',
			'root',
			$definition[ 'wp_cli_service' ],
			'sh',
			'-c',
			$script,
		] );
		$process = $this->processRunner->run(
			$command,
			$rootDir,
			null,
			$this->buildRuntimeEnvOverrides( $rootDir )
		);
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			throw $this->commandFailureException(
				'Failed to prepare cross-site update files on '.$site.'.',
				$command,
				$exitCode,
				$process->getOutput(),
				$process->getErrorOutput()
			);
		}
		return $process->getOutput();
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
		return \array_merge(
			$envOverrides,
			DockerCleanupPolicy::crossSite()->labelEnvironment(
				self::REUSABLE_DOCKER_RUN_ID,
				DockerHarnessLabels::LIFECYCLE_REUSABLE,
				'cross-site',
				self::REUSABLE_DOCKER_EXPIRES_AT,
				self::REUSABLE_DOCKER_RUN_ID,
				DockerHarnessLabels::LIFECYCLE_REUSABLE,
				self::REUSABLE_DOCKER_EXPIRES_AT
			)
		);
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
	private function buildProvisionCommand( string $site, bool $coreOnly = false ) :array {
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
			'SHIELD_LOCAL_SITE_PROVISION_MODE' => $coreOnly ? 'core-only' : 'current-runtime',
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
				'--user',
				'root',
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

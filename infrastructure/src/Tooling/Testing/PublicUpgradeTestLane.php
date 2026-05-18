<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class PublicUpgradeTestLane {

	public const EXIT_PASS = 0;
	public const EXIT_TEST_FAILURE = 1;
	public const EXIT_SETUP_FAILURE = 2;
	public const EXIT_VERSION_GATE = 3;

	private const COMPOSE_FILE = 'tests/docker/docker-compose.upgrade-public.yml';
	private const WORDPRESS_SERVICE = 'wordpress';
	private const WPCLI_SERVICE = 'wp-cli';
	private const PLUGIN_SLUG = 'wp-simple-firewall';
	private const PLUGIN_FILE = 'wp-simple-firewall/icwp-wpsf.php';
	private const REMOTE_ARTIFACT_DIR = '/var/www/html/wp-content/shield-upgrade-test';
	private const REMOTE_PACKAGE_DIR = '/var/www/html/wp-content/uploads/shield-upgrade-test';
	private const REMOTE_PACKAGE_PATH = self::REMOTE_PACKAGE_DIR.'/wp-simple-firewall-current.zip';
	private const INTERNAL_PACKAGE_URL = 'http://wordpress.test/wp-content/uploads/shield-upgrade-test/wp-simple-firewall-current.zip';

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalSiteProbe $probe;

	private PublicUpgradePackageZipResolver $packageZipResolver;

	private PublicUpgradeLogScanner $logScanner;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteProbe $probe = null,
		?PublicUpgradePackageZipResolver $packageZipResolver = null,
		?PublicUpgradeLogScanner $logScanner = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->probe = $probe ?? new LocalSiteProbe();
		$this->packageZipResolver = $packageZipResolver ?? new PublicUpgradePackageZipResolver( $this->processRunner );
		$this->logScanner = $logScanner ?? new PublicUpgradeLogScanner();
	}

	public function run(
		string $rootDir,
		?string $packageZip = null,
		?string $artifactDir = null,
		bool $showDockerOutput = false
	) :int {
		$artifacts = PublicUpgradeArtifacts::resolve( $rootDir, $artifactDir, $showDockerOutput );
		$artifacts->resetForRun();

		$summary = $this->baseSummary( $artifacts );
		$envOverrides = null;
		$exitCode = self::EXIT_SETUP_FAILURE;

		try {
			echo 'Mode: upgrade-public'.\PHP_EOL;
			echo 'Artifact directory: '.$artifacts->dir().\PHP_EOL;

			$this->environmentResolver->assertDockerReady( $rootDir );
			$envOverrides = $this->buildDockerEnvOverrides( $rootDir );
			$metadata = $this->packageZipResolver->resolve(
				$rootDir,
				$packageZip,
				$artifacts,
				$artifacts->processOutputCallback()
			);
			$summary[ 'package_zip' ] = $metadata->zipPath();
			$summary[ 'package_version' ] = $metadata->version();
			$summary[ 'package_plugin_file' ] = $metadata->pluginFile();

			$this->resetAndStartSite( $rootDir, $envOverrides, $artifacts, $showDockerOutput );
			$this->installWordPress( $rootDir, $envOverrides, $artifacts );
			$this->configureLogging( $rootDir, $envOverrides, $artifacts );
			$this->normalizeWordPressPermissions( $rootDir, $envOverrides, $artifacts );
			$this->installMuFixtures( $rootDir, $envOverrides, $artifacts );

			$publicInfo = $this->runJsonFixture( $rootDir, $envOverrides, $artifacts, 'public-plugin-info.php' );
			$summary[ 'public_info' ] = $publicInfo;
			$artifacts->writeJson( PublicUpgradeArtifacts::PUBLIC_VERSION_FILE, $publicInfo );

			$this->installPublicPlugin( $rootDir, $envOverrides, $artifacts );
			$publicVersion = $this->wpCapture(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'plugin', 'get', self::PLUGIN_SLUG, '--field=version' ],
				'Read installed public Shield version',
				true
			);
			$summary[ 'installed_public_version' ] = $publicVersion;
			$this->assertPublicVersionMatchesMetadata( $publicVersion, $publicInfo );
			if ( \version_compare( $metadata->version(), $publicVersion, '<=' ) ) {
				throw new PublicUpgradeVersionGateException(
					'Current package version '.$metadata->version().' is not greater than public version '.$publicVersion.'.'
				);
			}

			$this->publishPackageZip( $rootDir, $envOverrides, $artifacts, $metadata );
			$this->writeUpdateConfig( $rootDir, $envOverrides, $artifacts, $metadata );
			$this->verifyPackageUrl( $rootDir, $envOverrides, $artifacts );

			$primingReport = $this->runJsonFixture( $rootDir, $envOverrides, $artifacts, 'prime-shield-options.php' );
			$summary[ 'priming_report' ] = $primingReport;
			$artifacts->writeJson( PublicUpgradeArtifacts::PRIMING_REPORT_FILE, $primingReport );

			$updateResult = $this->runPluginUpdate( $rootDir, $envOverrides, $artifacts );
			$summary[ 'update_result' ] = $updateResult;
			$artifacts->writeJson( PublicUpgradeArtifacts::UPDATE_RESULT_FILE, $updateResult );
			$this->assertPluginUpdateResult( $updateResult, $publicVersion, $metadata->version() );

			$this->runWp(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'cron', 'event', 'run', '--due-now' ],
				'Run due WordPress cron events',
				true
			);

			$finalVersion = $this->wpCapture(
				$rootDir,
				$envOverrides,
				$artifacts,
				[ 'plugin', 'get', self::PLUGIN_SLUG, '--field=version' ],
				'Read final Shield version',
				true
			);
			$summary[ 'final_version' ] = $finalVersion;
			if ( $finalVersion !== $metadata->version() ) {
				throw new PublicUpgradeTestFailureException(
					'Final Shield version '.$finalVersion.' does not match package version '.$metadata->version().'.'
				);
			}

			$this->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, false );
			$summary[ 'log_findings' ] = $this->scanArtifacts( $artifacts, false );
			if ( $summary[ 'log_findings' ] !== [] ) {
				throw new PublicUpgradeTestFailureException( 'Upgrade logs contain fatal or Shield-scoped error output.' );
			}

			$summary[ 'status' ] = 'pass';
			$exitCode = self::EXIT_PASS;
			return $exitCode;
		}
		catch ( PublicUpgradeVersionGateException $e ) {
			$summary[ 'status' ] = 'version-gate';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_VERSION_GATE;
			echo 'Upgrade public version gate: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( PublicUpgradeTestFailureException $e ) {
			$summary[ 'status' ] = 'fail';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_TEST_FAILURE;
			echo 'Upgrade public test failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		catch ( \Throwable $e ) {
			$summary[ 'status' ] = 'setup-error';
			$summary[ 'message' ] = $e->getMessage();
			$exitCode = self::EXIT_SETUP_FAILURE;
			echo 'Upgrade public setup failed: '.$e->getMessage().\PHP_EOL;
			return $exitCode;
		}
		finally {
			if ( \is_array( $envOverrides ) ) {
				$includeDockerLogs = $exitCode !== self::EXIT_PASS;
				$this->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, $includeDockerLogs );
				if ( !isset( $summary[ 'log_findings' ] ) ) {
					$summary[ 'log_findings' ] = $this->scanArtifacts( $artifacts, $includeDockerLogs );
				}
				$this->dockerComposeExecutor->runIgnoringFailure(
					$rootDir,
					[ self::COMPOSE_FILE ],
					[ 'down', '-v', '--remove-orphans' ],
					$envOverrides,
					$showDockerOutput
				);
			}
			$summary[ 'exit_code' ] = $exitCode;
			$summary[ 'finished_at' ] = \gmdate( DATE_ATOM );
			$artifacts->writeJson( PublicUpgradeArtifacts::SUMMARY_FILE, $summary );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function baseSummary( PublicUpgradeArtifacts $artifacts ) :array {
		return [
			'status'       => 'running',
			'exit_code'    => self::EXIT_SETUP_FAILURE,
			'artifact_dir' => $artifacts->dir(),
			'started_at'   => \gmdate( DATE_ATOM ),
			'artifacts'    => [
				'summary'         => $artifacts->path( PublicUpgradeArtifacts::SUMMARY_FILE ),
				'public_version'  => $artifacts->path( PublicUpgradeArtifacts::PUBLIC_VERSION_FILE ),
				'priming_report'  => $artifacts->path( PublicUpgradeArtifacts::PRIMING_REPORT_FILE ),
				'update_result'   => $artifacts->path( PublicUpgradeArtifacts::UPDATE_RESULT_FILE ),
				'wp_cli_log'      => $artifacts->path( PublicUpgradeArtifacts::WP_CLI_LOG_FILE ),
				'wordpress_debug' => $artifacts->path( PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE ),
				'error_events'    => $artifacts->path( PublicUpgradeArtifacts::ERROR_EVENTS_FILE ),
				'docker_log'      => $artifacts->path( PublicUpgradeArtifacts::DOCKER_LOG_FILE ),
			],
		];
	}

	/**
	 * @return array<string,string|false>
	 */
	private function buildDockerEnvOverrides( string $rootDir ) :array {
		$composeProject = \trim( (string)( \getenv( 'SHIELD_UPGRADE_TEST_COMPOSE_PROJECT' ) ?: 'shield-upgrade-public' ) );
		if ( \preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $composeProject ) !== 1 ) {
			throw new \RuntimeException( 'SHIELD_UPGRADE_TEST_COMPOSE_PROJECT must use lowercase letters, numbers, underscore, or hyphen.' );
		}

		$sitePort = \trim( (string)( \getenv( 'SHIELD_UPGRADE_TEST_SITE_PORT' ) ?: '8894' ) );
		if ( \preg_match( '/^\d+$/', $sitePort ) !== 1 ) {
			throw new \RuntimeException( 'SHIELD_UPGRADE_TEST_SITE_PORT must be numeric.' );
		}

		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides( $composeProject, true );
		$envOverrides[ 'PHP_VERSION' ] = $this->environmentResolver->resolvePhpVersion( $rootDir );
		$envOverrides[ 'SHIELD_LOCAL_SITE_DB_NAME' ] = $this->dbNameFromComposeProject( $composeProject );
		$envOverrides[ 'SHIELD_LOCAL_SITE_PORT' ] = $sitePort;
		$envOverrides[ 'SHIELD_LOCAL_SITE_PROFILE' ] = 'upgrade-public';
		return $envOverrides;
	}

	private function dbNameFromComposeProject( string $composeProject ) :string {
		$dbName = \strtolower( \preg_replace( '/[^a-zA-Z0-9_]+/', '_', $composeProject ) ?? '' );
		$dbName = \trim( $dbName, '_' );
		return \substr( 'wp_'.$dbName, 0, 60 ) ?: 'wp_shield_upgrade_public';
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function resetAndStartSite(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		bool $showDockerOutput
	) :void {
		$this->dockerComposeExecutor->runIgnoringFailure(
			$rootDir,
			[ self::COMPOSE_FILE ],
			[ 'down', '-v', '--remove-orphans' ],
			$envOverrides,
			$showDockerOutput
		);
		$exitCode = $this->dockerComposeExecutor->run(
			$rootDir,
			[ self::COMPOSE_FILE ],
			[ 'up', '-d', 'db', self::WORDPRESS_SERVICE ],
			$envOverrides,
			$artifacts->processOutputCallback(),
			$showDockerOutput
		);
		if ( $exitCode !== 0 ) {
			throw new \RuntimeException( 'Failed to start upgrade-public Docker WordPress site.' );
		}
		if ( !$this->probe->waitForHttpReady( $this->siteUrl( $envOverrides ).'/wp-login.php', 90 ) ) {
			throw new \RuntimeException( 'Upgrade-public WordPress site did not become reachable.' );
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function installWordPress( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[
				'core',
				'install',
				'--url='.$this->siteUrl( $envOverrides ),
				'--title=Shield Upgrade Public Test',
				'--admin_user=admin',
				'--admin_password=password',
				'--admin_email=devnull@example.com',
				'--skip-email',
			],
			'Install clean WordPress site',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function configureLogging( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'eval', 'if ( ! is_dir( WP_CONTENT_DIR."/shield-upgrade-test" ) ) { mkdir( WP_CONTENT_DIR."/shield-upgrade-test", 0777, true ); }' ],
			'Create upgrade artifact directory in WordPress',
			false
		);

		foreach ( [
			[ 'WP_DEBUG', 'true', '--raw' ],
			[ 'WP_DEBUG_DISPLAY', 'false', '--raw' ],
			[ 'WP_DEBUG_LOG', self::REMOTE_ARTIFACT_DIR.'/'.PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE ],
			[ 'FS_METHOD', 'direct' ],
		] as $config ) {
			$this->runWp(
				$rootDir,
				$envOverrides,
				$artifacts,
				\array_merge( [ 'config', 'set' ], $config, [ '--type=constant' ] ),
				'Configure WordPress debug logging',
				false
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function normalizeWordPressPermissions(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts
	) :void {
		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[
				'exec',
				'-T',
				'--user',
				'root',
				self::WORDPRESS_SERVICE,
				'sh',
				'-c',
				'chown -R www-data:www-data /var/www/html/wp-content /var/www/html/wp-config.php && chmod -R u+rwX,g+rwX /var/www/html/wp-content /var/www/html/wp-config.php',
			],
			'Normalize WordPress volume permissions',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function installMuFixtures( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$script = 'mkdir -p /var/www/html/wp-content/mu-plugins'
			.' && cp /app/tests/fixtures/upgrade-public/update-provider.php /var/www/html/wp-content/mu-plugins/shield-upgrade-test-update-provider.php'
			.' && cp /app/tests/fixtures/upgrade-public/error-collector.php /var/www/html/wp-content/mu-plugins/shield-upgrade-test-error-collector.php';

		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'run', '--rm', '-T', '--user', 'root', self::WPCLI_SERVICE, 'sh', '-c', $script ],
			'Install upgrade-test MU fixtures',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function installPublicPlugin( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'install', self::PLUGIN_SLUG, '--activate' ],
			'Install latest public Shield from WordPress.org',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function publishPackageZip(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'cp', $metadata->zipPath(), self::WORDPRESS_SERVICE.':/tmp/shield-upgrade-package.zip' ],
			'Copy package zip into WordPress container',
			false
		);
		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[
				'exec',
				'-T',
				self::WORDPRESS_SERVICE,
				'sh',
				'-c',
				'mkdir -p '.self::REMOTE_PACKAGE_DIR.' && cp /tmp/shield-upgrade-package.zip '.self::REMOTE_PACKAGE_PATH,
			],
			'Publish package zip through WordPress HTTP server',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function writeUpdateConfig(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		$config = [
			'plugin'      => self::PLUGIN_FILE,
			'slug'        => self::PLUGIN_SLUG,
			'id'          => self::PLUGIN_SLUG,
			'new_version' => $metadata->version(),
			'package'     => self::INTERNAL_PACKAGE_URL,
			'url'         => 'https://wordpress.org/plugins/'.self::PLUGIN_SLUG.'/',
		];
		$this->runJsonFixture(
			$rootDir,
			$envOverrides,
			$artifacts,
			'write-update-config.php',
			[ \base64_encode( \json_encode( $config, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) ) ]
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function verifyPackageUrl( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :void {
		$result = $this->runJsonFixture( $rootDir, $envOverrides, $artifacts, 'verify-package-url.php' );
		if ( !( $result[ 'ok' ] ?? false ) ) {
			throw new PublicUpgradeTestFailureException(
				'Package URL is not reachable from WordPress: '.(string)( $result[ 'message' ] ?? 'unknown error' )
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @return array<string,mixed>
	 */
	private function runPluginUpdate( string $rootDir, array $envOverrides, PublicUpgradeArtifacts $artifacts ) :array {
		$process = $this->runWpAllowFailure(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'update', self::PLUGIN_SLUG, '--format=json' ],
			'Run normal WordPress plugin update'
		);
		$output = \trim( $process->getOutput() );
		$decoded = \json_decode( $output, true );
		if ( !\is_array( $decoded ) ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update did not return valid JSON.' );
		}

		if ( $this->isList( $decoded ) ) {
			$row = $decoded[ 0 ] ?? null;
			if ( !\is_array( $row ) ) {
				throw new PublicUpgradeTestFailureException( 'Plugin update JSON did not contain a result row.' );
			}
			$result = $row;
		}
		else {
			$result = $decoded;
		}

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new PublicUpgradeTestFailureException(
				'WordPress plugin update failed with status '.(string)( $result[ 'status' ] ?? 'missing' ).'.'
			);
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $publicInfo
	 */
	private function assertPublicVersionMatchesMetadata( string $publicVersion, array $publicInfo ) :void {
		$expectedPublicVersion = (string)( $publicInfo[ 'version' ] ?? '' );
		if ( $expectedPublicVersion === '' ) {
			throw new \RuntimeException( 'WordPress.org plugin metadata did not include a version.' );
		}
		if ( $publicVersion !== $expectedPublicVersion ) {
			throw new PublicUpgradeTestFailureException(
				'Installed public Shield version '.$publicVersion.' does not match WordPress.org version '.$expectedPublicVersion.'.'
			);
		}
	}

	/**
	 * @param array<mixed> $value
	 */
	private function isList( array $value ) :bool {
		return $value === [] || \array_keys( $value ) === \range( 0, \count( $value ) - 1 );
	}

	/**
	 * @param array<string,mixed> $result
	 */
	private function assertPluginUpdateResult( array $result, string $publicVersion, string $packageVersion ) :void {
		if ( (string)( $result[ 'status' ] ?? '' ) !== 'Updated' ) {
			throw new PublicUpgradeTestFailureException(
				'Plugin update status was '.(string)( $result[ 'status' ] ?? 'missing' ).', expected Updated.'
			);
		}
		if ( (string)( $result[ 'old_version' ] ?? '' ) !== $publicVersion ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update old_version did not match public version.' );
		}
		if ( (string)( $result[ 'new_version' ] ?? '' ) !== $packageVersion ) {
			throw new PublicUpgradeTestFailureException( 'Plugin update new_version did not match package version.' );
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $args
	 * @return array<string,mixed>
	 */
	private function runJsonFixture(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		string $fixture,
		array $args = []
	) :array {
		$output = $this->wpCapture(
			$rootDir,
			$envOverrides,
			$artifacts,
			\array_merge( [ 'eval-file', '/app/tests/fixtures/upgrade-public/'.$fixture ], $args ),
			'Run '.$fixture,
			true
		);

		$decoded = \json_decode( $output, true );
		if ( !\is_array( $decoded ) ) {
			throw new PublicUpgradeTestFailureException( 'Fixture '.$fixture.' did not return a JSON object.' );
		}

		return $decoded;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $wpCliArgs
	 */
	private function runWp(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		array $wpCliArgs,
		string $label,
		bool $testFailureOnError
	) :Process {
		return $this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			\array_merge(
				[ 'run', '--rm', '-T', '--user', 'root', self::WPCLI_SERVICE, 'wp' ],
				$this->withAllowRoot( $wpCliArgs )
			),
			$label,
			$testFailureOnError
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $wpCliArgs
	 */
	private function runWpAllowFailure(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		array $wpCliArgs,
		string $label
	) :Process {
		return $this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			\array_merge(
				[ 'run', '--rm', '-T', '--user', 'root', self::WPCLI_SERVICE, 'wp' ],
				$this->withAllowRoot( $wpCliArgs )
			),
			$label,
			false,
			true
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $wpCliArgs
	 */
	private function wpCapture(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		array $wpCliArgs,
		string $label,
		bool $testFailureOnError
	) :string {
		return \trim( $this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			$wpCliArgs,
			$label,
			$testFailureOnError
		)->getOutput() );
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $subCommand
	 */
	private function runComposeProcess(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		array $subCommand,
		string $label,
		bool $testFailureOnError,
		bool $allowFailure = false
	) :Process {
		$artifacts->appendLogHeading( $label );
		$process = $this->processRunner->run(
			$this->buildComposeCommand( $subCommand ),
			$rootDir,
			$artifacts->processOutputCallback(),
			$envOverrides
		);
		if ( !$allowFailure && ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$message = $label.' failed with exit code '.( $process->getExitCode() ?? 1 ).'.';
			if ( $testFailureOnError ) {
				throw new PublicUpgradeTestFailureException( $message );
			}
			throw new \RuntimeException( $message );
		}

		return $process;
	}

	/**
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function buildComposeCommand( array $subCommand ) :array {
		return \array_merge( [ 'docker', 'compose', '-f', self::COMPOSE_FILE ], $subCommand );
	}

	/**
	 * @param string[] $wpCliArgs
	 * @return string[]
	 */
	private function withAllowRoot( array $wpCliArgs ) :array {
		if ( !\in_array( '--allow-root', $wpCliArgs, true ) ) {
			$wpCliArgs[] = '--allow-root';
		}
		return $wpCliArgs;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function siteUrl( array $envOverrides ) :string {
		return 'http://127.0.0.1:'.(string)( $envOverrides[ 'SHIELD_LOCAL_SITE_PORT' ] ?? '8894' );
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function collectRuntimeArtifacts(
		string $rootDir,
		array $envOverrides,
		PublicUpgradeArtifacts $artifacts,
		bool $includeDockerLogs
	) :void {
		$this->copyContainerFile(
			$rootDir,
			$envOverrides,
			self::REMOTE_ARTIFACT_DIR.'/'.PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE,
			$artifacts->path( PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE )
		);
		$this->copyContainerFile(
			$rootDir,
			$envOverrides,
			self::REMOTE_ARTIFACT_DIR.'/'.PublicUpgradeArtifacts::ERROR_EVENTS_FILE,
			$artifacts->path( PublicUpgradeArtifacts::ERROR_EVENTS_FILE )
		);
		$artifacts->ensureFileExists( PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE );
		$artifacts->ensureFileExists( PublicUpgradeArtifacts::ERROR_EVENTS_FILE );

		if ( !$includeDockerLogs ) {
			return;
		}

		$process = $this->processRunner->run(
			$this->buildComposeCommand( [ 'logs', '--no-color', self::WORDPRESS_SERVICE, 'db' ] ),
			$rootDir,
			static function () :void {},
			$envOverrides
		);
		if ( ( $process->getExitCode() ?? 1 ) === 0 ) {
			\file_put_contents(
				$artifacts->path( PublicUpgradeArtifacts::DOCKER_LOG_FILE ),
				$process->getOutput().$process->getErrorOutput()
			);
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function copyContainerFile(
		string $rootDir,
		array $envOverrides,
		string $remotePath,
		string $localPath
	) :void {
		$this->processRunner->run(
			$this->buildComposeCommand( [ 'cp', self::WORDPRESS_SERVICE.':'.$remotePath, $localPath ] ),
			$rootDir,
			static function () :void {},
			$envOverrides
		);
	}

	/**
	 * @return array<int,array{file:string,line:int,reason:string,message:string}>
	 */
	private function scanArtifacts( PublicUpgradeArtifacts $artifacts, bool $includeDockerLog ) :array {
		$paths = [
			$artifacts->path( PublicUpgradeArtifacts::WP_CLI_LOG_FILE ),
			$artifacts->path( PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE ),
			$artifacts->path( PublicUpgradeArtifacts::ERROR_EVENTS_FILE ),
		];
		if ( $includeDockerLog ) {
			$paths[] = $artifacts->path( PublicUpgradeArtifacts::DOCKER_LOG_FILE );
		}

		return $this->logScanner->scanFiles( $paths );
	}
}

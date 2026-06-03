<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class WordPressPackageRuntimeHarness {

	public const COMPOSE_FILE = 'tests/docker/docker-compose.upgrade-public.yml';
	public const WORDPRESS_SERVICE = 'wordpress';
	public const WPCLI_SERVICE = 'wp-cli';
	public const REMOTE_ARTIFACT_DIR = '/var/www/html/wp-content/shield-runtime-test';
	public const REMOTE_ARTIFACT_SUBDIR = 'shield-runtime-test';
	public const REMOTE_PACKAGE_DIR = '/var/www/html/wp-content/uploads/shield-package-runtime-test';
	public const REMOTE_PACKAGE_PATH = self::REMOTE_PACKAGE_DIR.'/wp-simple-firewall-current.zip';
	public const INTERNAL_PACKAGE_URL = 'http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip';

	private const REMOTE_ARTIFACT_MISSING_EXIT = 44;

	private ProcessRunner $processRunner;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $dockerComposeExecutor;

	private LocalSiteProbe $probe;

	private PackageRuntimeLogScanner $logScanner;

	private string $mode;

	private string $siteTitle;

	private string $composeProjectEnv;

	private string $defaultComposeProject;

	private string $sitePortEnv;

	private string $defaultSitePort;

	public function __construct(
		string $mode,
		string $siteTitle,
		string $composeProjectEnv,
		string $defaultComposeProject,
		string $sitePortEnv,
		string $defaultSitePort,
		?ProcessRunner $processRunner = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $dockerComposeExecutor = null,
		?LocalSiteProbe $probe = null,
		?PackageRuntimeLogScanner $logScanner = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver( $this->processRunner );
		$this->dockerComposeExecutor = $dockerComposeExecutor ?? new DockerComposeExecutor( $this->processRunner );
		$this->probe = $probe ?? new LocalSiteProbe();
		$this->logScanner = $logScanner ?? new PackageRuntimeLogScanner();
		$this->mode = $mode;
		$this->siteTitle = $siteTitle;
		$this->composeProjectEnv = $composeProjectEnv;
		$this->defaultComposeProject = $defaultComposeProject;
		$this->sitePortEnv = $sitePortEnv;
		$this->defaultSitePort = $defaultSitePort;
	}

	public function assertDockerReady( string $rootDir ) :void {
		$this->environmentResolver->assertDockerReady( $rootDir );
	}

	/**
	 * @return array<string,string|false>
	 */
	public function buildDockerEnvOverrides( string $rootDir ) :array {
		$composeProject = \trim( (string)( \getenv( $this->composeProjectEnv ) ?: $this->defaultComposeProject ) );
		if ( \preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $composeProject ) !== 1 ) {
			throw new \RuntimeException( $this->composeProjectEnv.' must use lowercase letters, numbers, underscore, or hyphen.' );
		}

		$sitePort = \trim( (string)( \getenv( $this->sitePortEnv ) ?: $this->defaultSitePort ) );
		if ( \preg_match( '/^\d+$/', $sitePort ) !== 1 ) {
			throw new \RuntimeException( $this->sitePortEnv.' must be numeric.' );
		}

		$envOverrides = $this->environmentResolver->buildDockerProcessEnvOverrides( $composeProject, true );
		$envOverrides[ 'PHP_VERSION' ] = $this->environmentResolver->resolvePhpVersion( $rootDir );
		$envOverrides[ 'SHIELD_LOCAL_SITE_DB_NAME' ] = $this->dbNameFromComposeProject( $composeProject );
		$envOverrides[ 'SHIELD_LOCAL_SITE_PORT' ] = $sitePort;
		$envOverrides[ 'SHIELD_LOCAL_SITE_PROFILE' ] = $this->mode;
		return $envOverrides;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function resetAndStartSite(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
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
			throw new \RuntimeException( 'Failed to start '.$this->mode.' Docker WordPress site.' );
		}
		if ( !$this->probe->waitForHttpReady( $this->siteUrl( $envOverrides ).'/wp-login.php', 90 ) ) {
			throw new \RuntimeException( $this->mode.' WordPress site did not become reachable.' );
		}
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function prepareCleanSite(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		bool $showDockerOutput,
		bool $includeUpdateProvider
	) :void {
		$this->resetAndStartSite( $rootDir, $envOverrides, $artifacts, $showDockerOutput );
		$this->installWordPress( $rootDir, $envOverrides, $artifacts );
		$this->configureLogging( $rootDir, $envOverrides, $artifacts );
		$this->normalizeWordPressPermissions( $rootDir, $envOverrides, $artifacts );
		$this->installMuFixtures( $rootDir, $envOverrides, $artifacts, $includeUpdateProvider );
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function installWordPress(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts
	) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[
				'core',
				'install',
				'--url='.$this->siteUrl( $envOverrides ),
				'--title='.$this->siteTitle,
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
	public function configureLogging(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts
	) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'eval', 'if ( ! is_dir( WP_CONTENT_DIR."/'.self::REMOTE_ARTIFACT_SUBDIR.'" ) ) { mkdir( WP_CONTENT_DIR."/'.self::REMOTE_ARTIFACT_SUBDIR.'", 0777, true ); }' ],
			'Create runtime artifact directory in WordPress',
			false
		);

		foreach ( [
			[ 'WP_DEBUG', 'true', '--raw' ],
			[ 'WP_DEBUG_DISPLAY', 'false', '--raw' ],
			[ 'WP_DEBUG_LOG', self::REMOTE_ARTIFACT_DIR.'/'.WordPressPackageRuntimeArtifacts::WORDPRESS_DEBUG_LOG_FILE ],
			[ 'WP_MEMORY_LIMIT', '512M' ],
			[ 'WP_MAX_MEMORY_LIMIT', '512M' ],
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
	public function normalizeWordPressPermissions(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts
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
	public function installMuFixtures(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		bool $includeUpdateProvider
	) :void {
		$script = 'mkdir -p /var/www/html/wp-content/mu-plugins'
			.' && cp /app/tests/fixtures/runtime/error-collector.php /var/www/html/wp-content/mu-plugins/shield-runtime-test-error-collector.php';
		if ( $includeUpdateProvider ) {
			$script .= ' && cp /app/tests/fixtures/upgrade-public/update-provider.php /var/www/html/wp-content/mu-plugins/shield-upgrade-test-update-provider.php';
		}

		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'run', '--rm', '-T', '--user', 'root', self::WPCLI_SERVICE, 'sh', '-c', $script ],
			'Install runtime MU fixtures',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function publishPackageZip(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		PublicUpgradePackageZipMetadata $metadata
	) :void {
		$this->runComposeProcess(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'cp', $metadata->zipPath(), self::WORDPRESS_SERVICE.':/tmp/shield-package-runtime.zip' ],
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
				'mkdir -p '.self::REMOTE_PACKAGE_DIR.' && cp /tmp/shield-package-runtime.zip '.self::REMOTE_PACKAGE_PATH,
			],
			'Publish package zip through WordPress HTTP server',
			false
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function installPublishedPackageZip(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $label
	) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'install', self::REMOTE_PACKAGE_PATH, '--activate' ],
			$label,
			true
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function runDueCron(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts
	) :void {
		$this->runWp(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'cron', 'event', 'run', '--due-now' ],
			'Run due WordPress cron events',
			true
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function readPluginVersion(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $slug,
		string $label
	) :string {
		return $this->wpCapture(
			$rootDir,
			$envOverrides,
			$artifacts,
			[ 'plugin', 'get', $slug, '--field=version' ],
			$label,
			true
		);
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function assertPluginVersion(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $slug,
		string $expectedVersion,
		string $label,
		string $messagePrefix
	) :string {
		$version = $this->readPluginVersion( $rootDir, $envOverrides, $artifacts, $slug, $label );
		if ( $version !== $expectedVersion ) {
			throw new PackageRuntimeTestFailureException(
				$messagePrefix.' '.$version.' does not match package version '.$expectedVersion.'.'
			);
		}
		return $version;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[]                   $wpCliArgs
	 * @return array<mixed>
	 */
	public function runJsonWpCommand(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		array $wpCliArgs,
		string $label
	) :array {
		$output = $this->wpCapture(
			$rootDir,
			$envOverrides,
			$artifacts,
			$wpCliArgs,
			$label,
			true
		);
		$decoded = \json_decode( $output, true );
		if ( !\is_array( $decoded ) ) {
			throw new PackageRuntimeTestFailureException( $label.' did not return valid JSON.' );
		}

		return $decoded;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $args
	 * @return array<string,mixed>
	 */
	public function runJsonFixture(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $fixturePath,
		array $args = []
	) :array {
		$output = $this->wpCapture(
			$rootDir,
			$envOverrides,
			$artifacts,
			\array_merge( [ 'eval-file', $fixturePath ], $args ),
			'Run '.\basename( $fixturePath ),
			true
		);

		$decoded = \json_decode( $output, true );
		if ( !\is_array( $decoded ) ) {
			throw new PackageRuntimeTestFailureException( 'Fixture '.\basename( $fixturePath ).' did not return a JSON object.' );
		}

		return $decoded;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $wpCliArgs
	 */
	public function runWp(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
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
	public function runWpAllowFailure(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
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
	public function wpCapture(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
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
	 */
	public function collectRuntimeArtifacts(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		bool $includeDockerLogs,
		bool $strictRuntimeArtifacts
	) :void {
		$this->copyOptionalRuntimeArtifact(
			$rootDir,
			$envOverrides,
			$artifacts,
			self::REMOTE_ARTIFACT_DIR.'/'.WordPressPackageRuntimeArtifacts::WORDPRESS_DEBUG_LOG_FILE,
			WordPressPackageRuntimeArtifacts::WORDPRESS_DEBUG_LOG_FILE,
			$strictRuntimeArtifacts
		);
		$this->copyOptionalRuntimeArtifact(
			$rootDir,
			$envOverrides,
			$artifacts,
			self::REMOTE_ARTIFACT_DIR.'/'.WordPressPackageRuntimeArtifacts::ERROR_EVENTS_FILE,
			WordPressPackageRuntimeArtifacts::ERROR_EVENTS_FILE,
			$strictRuntimeArtifacts
		);

		if ( !$includeDockerLogs ) {
			return;
		}

		try {
			$process = $this->processRunner->run(
				$this->buildComposeCommand( [ 'logs', '--no-color', self::WORDPRESS_SERVICE, 'db' ] ),
				$rootDir,
				static function () :void {},
				$envOverrides
			);
			if ( ( $process->getExitCode() ?? 1 ) === 0 ) {
				\file_put_contents(
					$artifacts->path( WordPressPackageRuntimeArtifacts::DOCKER_LOG_FILE ),
					$process->getOutput().$process->getErrorOutput()
				);
			}
		}
		catch ( \Throwable $e ) {
			$artifacts->appendWpCliLog(
				\PHP_EOL.'Warning: Docker log collection failed: '.$e->getMessage().\PHP_EOL
			);
		}
	}

	/**
	 * @return array<int,array{file:string,line:int,reason:string,message:string}>
	 */
	public function scanArtifacts( WordPressPackageRuntimeArtifacts $artifacts, bool $includeDockerLog ) :array {
		$paths = [
			$artifacts->path( WordPressPackageRuntimeArtifacts::WP_CLI_LOG_FILE ),
			$artifacts->path( WordPressPackageRuntimeArtifacts::WORDPRESS_DEBUG_LOG_FILE ),
			$artifacts->path( WordPressPackageRuntimeArtifacts::ERROR_EVENTS_FILE ),
		];
		if ( $includeDockerLog ) {
			$paths[] = $artifacts->path( WordPressPackageRuntimeArtifacts::DOCKER_LOG_FILE );
		}

		return $this->logScanner->scanFiles( $paths );
	}

	/**
	 * @param array<int,array{file:string,line:int,reason:string,message:string}> $findings
	 */
	public function hasGlobalFatalFinding( array $findings ) :bool {
		return $this->logScanner->hasGlobalFatalFinding( $findings );
	}

	/**
	 * @param array<string,string|false>|null $envOverrides
	 * @param array<string,mixed>             $summary
	 */
	public function finishRun(
		string $rootDir,
		?array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		bool $showDockerOutput,
		int $exitCode,
		bool $runtimeArtifactsCollected,
		array $summary
	) :void {
		if ( \is_array( $envOverrides ) ) {
			$includeDockerLogs = $exitCode !== 0;
			if ( !$runtimeArtifactsCollected ) {
				$this->collectRuntimeArtifacts( $rootDir, $envOverrides, $artifacts, $includeDockerLogs, false );
			}
			if ( !isset( $summary[ 'log_findings' ] ) ) {
				$summary[ 'log_findings' ] = $this->scanArtifacts( $artifacts, $includeDockerLogs );
			}
			$this->shutdown( $rootDir, $envOverrides, $showDockerOutput );
		}
		$summary[ 'exit_code' ] = $exitCode;
		$summary[ 'finished_at' ] = \gmdate( DATE_ATOM );
		$artifacts->writeSummary( $summary );
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	public function shutdown( string $rootDir, array $envOverrides, bool $showDockerOutput ) :void {
		$this->dockerComposeExecutor->runIgnoringFailure(
			$rootDir,
			[ self::COMPOSE_FILE ],
			[ 'down', '-v', '--remove-orphans' ],
			$envOverrides,
			$showDockerOutput
		);
	}

	public function internalPackageUrl() :string {
		return self::INTERNAL_PACKAGE_URL;
	}

	/**
	 * @param string[] $subCommand
	 * @return string[]
	 */
	public function buildComposeCommand( array $subCommand ) :array {
		return \array_merge( [ 'docker', 'compose', '-f', self::COMPOSE_FILE ], $subCommand );
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 * @param string[] $subCommand
	 */
	private function runComposeProcess(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
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
				throw new PackageRuntimeTestFailureException( $message );
			}
			throw new \RuntimeException( $message );
		}

		return $process;
	}

	/**
	 * @param array<string,string|false> $envOverrides
	 */
	private function copyOptionalRuntimeArtifact(
		string $rootDir,
		array $envOverrides,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $remotePath,
		string $artifactFile,
		bool $strict
	) :void {
		try {
			$probe = $this->processRunner->run(
				$this->buildComposeCommand( [
					'exec',
					'-T',
					self::WORDPRESS_SERVICE,
					'sh',
					'-c',
					'test -f "$1" || exit '.self::REMOTE_ARTIFACT_MISSING_EXIT,
					'sh',
					$remotePath,
				] ),
				$rootDir,
				static function () :void {},
				$envOverrides
			);
		}
		catch ( \Throwable $e ) {
			$this->handleRuntimeArtifactCollectionFailure(
				$strict,
				$artifacts,
				$artifactFile,
				'remote file probe could not run: '.$e->getMessage()
			);
			return;
		}

		$probeExit = $probe->getExitCode() ?? 1;
		if ( $probeExit === self::REMOTE_ARTIFACT_MISSING_EXIT ) {
			$artifacts->ensureFileExists( $artifactFile );
			return;
		}
		if ( $probeExit !== 0 ) {
			$this->handleRuntimeArtifactCollectionFailure(
				$strict,
				$artifacts,
				$artifactFile,
				'remote file probe failed with exit code '.$probeExit.'.'
			);
			return;
		}

		try {
			$copy = $this->processRunner->run(
				$this->buildComposeCommand( [ 'cp', self::WORDPRESS_SERVICE.':'.$remotePath, $artifacts->path( $artifactFile ) ] ),
				$rootDir,
				static function () :void {},
				$envOverrides
			);
		}
		catch ( \Throwable $e ) {
			$this->handleRuntimeArtifactCollectionFailure(
				$strict,
				$artifacts,
				$artifactFile,
				'remote file copy could not run: '.$e->getMessage()
			);
			return;
		}

		$copyExit = $copy->getExitCode() ?? 1;
		if ( $copyExit !== 0 ) {
			$this->handleRuntimeArtifactCollectionFailure(
				$strict,
				$artifacts,
				$artifactFile,
				'remote file copy failed with exit code '.$copyExit.'.'
			);
		}
	}

	private function handleRuntimeArtifactCollectionFailure(
		bool $strict,
		WordPressPackageRuntimeArtifacts $artifacts,
		string $artifactFile,
		string $message
	) :void {
		$message = 'Runtime artifact collection failed for '.$artifactFile.': '.$message;
		if ( $strict ) {
			throw new PackageRuntimeTestFailureException( $message );
		}

		$artifacts->appendWpCliLog( \PHP_EOL.'Warning: '.$message.\PHP_EOL );
		$artifacts->ensureFileExists( $artifactFile );
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
		return 'http://127.0.0.1:'.(string)( $envOverrides[ 'SHIELD_LOCAL_SITE_PORT' ] ?? $this->defaultSitePort );
	}

	private function dbNameFromComposeProject( string $composeProject ) :string {
		$dbName = \strtolower( \preg_replace( '/[^a-zA-Z0-9_]+/', '_', $composeProject ) ?? '' );
		$dbName = \trim( $dbName, '_' );
		return \substr( 'wp_'.$dbName, 0, 60 ) ?: 'wp_'.$this->mode;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSitePairManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadata;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;
use FernleafSystems\ShieldPlatform\Tooling\Testing\WordPressPackageRuntimeArtifacts;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class CrossSitePairManagerTest extends TestCase {

	use TempDirLifecycleTrait;

	private const MASTER_INTERNAL_URL = 'http://wordpress-master.shield-cross-site.example.com';
	private const SLAVE_INTERNAL_URL = 'http://wordpress-slave.shield-cross-site.example.com';

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
		$this->assertContains( 'SHIELD_LOCAL_SITE_URL='.self::MASTER_INTERNAL_URL, $command );
		$this->assertContains( 'SHIELD_LOCAL_SITE_PROFILE=cross-site-master', $command );
		$this->assertContains( 'wp-cli-master', $command );
		$this->assertContains( '/app/tests/docker/provision-local-site.sh', $command );
		$this->assertContains( 'SHIELD_LOCAL_SITE_PROVISION_MODE=current-runtime', $command );
	}

	public function testProvisionCommandSupportsCoreOnlyMode() :void {
		$command = $this->invokePrivate(
			new CrossSitePairManager(),
			'buildProvisionCommand',
			[ 'master', true ]
		);

		$this->assertContains( 'SHIELD_LOCAL_SITE_PROVISION_MODE=core-only', $command );
		$this->assertContains( '/app/tests/docker/provision-local-site.sh', $command );
	}

	public function testPublicInstallUsesPinnedReleaseAndProvesVersion() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-public-install-' );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "22.1.3\n" ],
		] );
		$manager = new CrossSitePairManager( $runner );

		$this->invokePrivate( $manager, 'installPublicPlugin', [ $root, 'master' ] );

		$this->findProcessCommandContaining(
			$runner,
			'plugin install wp-simple-firewall --version=22.1.3 --activate --force'
		);
		$install = $this->findProcessCommandContaining( $runner, 'plugin install wp-simple-firewall' );
		$this->assertSame( 'docker', $install[ 0 ] );
		$this->assertContains( 'compose', $install );
		$this->assertContains( 'run', $install );
		$this->assertContains( '--rm', $install );
		$this->assertContains( '--user', $install );
		$this->assertContains( 'root', $install );
		$this->assertContains( 'wp-cli-master', $install );
		$this->assertNotContains( '--volume', $install );
		$this->findProcessCommandContaining(
			$runner,
			'plugin get wp-simple-firewall --field=version'
		);
		$this->assertCount( 2, $runner->calls );
	}

	public function testAutomaticCronBlockerFixtureIsInstalledOnBothCrossSiteRuntimes() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-cron-blocker-' );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
		] );
		$manager = new CrossSitePairManager( $runner );

		$this->invokePrivate( $manager, 'installAutomaticCronBlockerFixture', [ $root ] );

		$copies = \array_values( \array_filter( $runner->calls, static function( array $call ) :bool {
			return \str_contains(
				\implode( ' ', $call[ 'command' ] ),
				'cp /app/tests/fixtures/cross-site/block-automatic-cron.php /var/www/html/wp-content/mu-plugins/shield-cross-site-block-automatic-cron.php'
			);
		} ) );
		$this->assertCount( 2, $copies );
		foreach ( [ 'master', 'slave' ] as $site ) {
			$this->assertTrue( \in_array( 'wp-cli-'.$site, $copies[ $site === 'master' ? 0 : 1 ][ 'command' ], true ) );
		}
		foreach ( $copies as $copy ) {
			$this->assertContains( '--user', $copy[ 'command' ] );
			$this->assertContains( 'root', $copy[ 'command' ] );
		}
		$this->assertCount( 2, $runner->calls );
	}

	public function testAutomaticCronBlockerFixtureScopesOnlyAutomaticLoopbackCronRequests() :void {
		$fixture = $this->readProjectFile( 'tests/fixtures/cross-site/block-automatic-cron.php' );

		foreach ( [ 'pre_http_request', 'home_url()', 'wp-cron.php', 'doing_wp_cron', 'new \\WP_Error' ] as $required ) {
			$this->assertStringContainsString( $required, $fixture );
		}
		foreach ( [ 'wp_schedule_', 'wp_clear_scheduled_', 'cron event' ] as $prohibited ) {
			$this->assertStringNotContainsString( $prohibited, $fixture );
		}
	}

	public function testPublicRuntimeFixtureIsInstalledAndRemovedOnPublicSetupFailure() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-public-runtime-' );
		$metadata = new PublicUpgradePackageZipMetadata(
			$root.'/wp-simple-firewall-current.zip',
			'23.0.0',
			'wp-simple-firewall/icwp-wpsf.php'
		);
		$resolver = new class( $metadata ) extends PublicUpgradePackageZipResolver {

			private PublicUpgradePackageZipMetadata $metadata;

			public function __construct( PublicUpgradePackageZipMetadata $metadata ) {
				$this->metadata = $metadata;
			}

			public function resolve(
				string $rootDir,
				?string $packageZip,
				WordPressPackageRuntimeArtifacts $artifacts,
				?callable $onOutput = null
			) :PublicUpgradePackageZipMetadata {
				return $this->metadata;
			}
		};
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "22.1.3\n" ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "22.1.3\n" ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 1 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
		] );
		$manager = new CrossSitePairManager( $runner, null, null, null, null, $resolver );

		try {
			$manager->runPublicUpgradeScenario( $root );
			$this->fail( 'Expected the public relation command to fail.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'WP-CLI command failed on slave', $exception->getMessage() );
		}

		$commands = \array_map( static fn( array $call ) :string => \implode( ' ', $call[ 'command' ] ), $runner->calls );
		$this->assertCount( 2, \array_filter(
			$commands,
			static fn( string $command ) :bool => \str_contains(
				$command,
				'cp /app/tests/fixtures/cross-site/public-22.1.3-runtime.php /var/www/html/wp-content/mu-plugins/shield-cross-site-public-22.1.3-runtime.php'
			)
		) );
		$this->assertCount( 2, \array_filter(
			$commands,
			static fn( string $command ) :bool => \str_contains(
				$command,
				'rm -f /var/www/html/wp-content/mu-plugins/shield-cross-site-public-22.1.3-runtime.php'
			)
		) );
		$this->assertCount( 2, \array_filter(
			$commands,
			static fn( string $command ) :bool => \str_contains(
				$command,
				'shield pro-license --action=activate --api-key=shield-cross-site-public-license-key --force'
			)
		) );
		$relation = $this->findProcessCommandContaining(
			$runner,
			'shield import --source='.self::MASTER_INTERNAL_URL.' --site-secret=0123456789abcdef0123456789abcdef01234567 --slave=add --force'
		);
		$this->assertContains( 'wp-cli-slave', $relation );
		$this->assertSame( '--allow-root', $relation[ \count( $relation ) - 1 ] );
		foreach ( \array_slice( $commands, 6 ) as $command ) {
			$this->assertStringNotContainsString( 'eval-file /app/tests/Helpers/CrossSite/CrossSiteRuntime.php', $command );
			$this->assertStringNotContainsString( 'enable-public-cli', $command );
			$this->assertStringNotContainsString( 'run-notify-hook', $command );
		}
	}

	public function testPublicOptionCommandsUseLegacyCliShapeAndCaptureVisibleValues() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-public-options-' );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "Current value: Y\n" ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "Current value: disabled\n" ],
			[ 'exit_code' => 0, 'stdout' => "Current value: AUTO_DETECT_IP\n" ],
			[ 'exit_code' => 0, 'stdout' => "Current value: N\n" ],
		] );
		$manager = new CrossSitePairManager( $runner );

		$this->invokePrivate( $manager, 'setShieldOptionViaCli', [ $root, 'master', 'importexport_enable', 'Y' ] );
		$this->invokePrivate( $manager, 'assertPublicCliOption', [ $root, 'master', 'importexport_enable', 'Y' ] );
		foreach ( [
			'display_plugin_badge' => 'disabled',
			'visitor_address_source' => 'AUTO_DETECT_IP',
			'enable_tracking' => 'N',
		] as $key => $value ) {
			$this->invokePrivate( $manager, 'setShieldOptionViaCli', [ $root, 'slave', $key, $value ] );
		}
		$snapshot = $this->invokePrivate( $manager, 'publicCliOptionsSnapshot', [ $root, 'slave' ] );

		$this->assertSame( [
			'display_plugin_badge' => 'disabled',
			'visitor_address_source' => 'AUTO_DETECT_IP',
			'enable_tracking' => 'N',
		], $snapshot );
		$this->findProcessCommandContaining( $runner, 'shield opt-set --key=importexport_enable --value=Y' );
		$this->findProcessCommandContaining( $runner, 'shield opt-set --key=display_plugin_badge --value=disabled' );
		$this->findProcessCommandContaining( $runner, 'shield opt-set --key=visitor_address_source --value=AUTO_DETECT_IP' );
		$this->findProcessCommandContaining( $runner, 'shield opt-set --key=enable_tracking --value=N' );
		foreach ( $runner->calls as $call ) {
			$this->assertStringNotContainsString( 'xfer_excluded', \implode( ' ', $call[ 'command' ] ) );
		}
	}

	public function testPublicSlaveSnapshotReadsTransferExcludedValuesThroughPublicCli() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-public-slave-snapshot-' );
		$runner = RecordingProcessRunner::strict( [
			$this->helperSuccessProcess( [ 'master_url' => self::MASTER_INTERNAL_URL ] ),
			$this->helperSuccessProcess( [
				'master_url' => self::MASTER_INTERNAL_URL,
				'import_id' => 'public-slave-import-id',
			] ),
			[ 'exit_code' => 0, 'stdout' => "Current value: disabled\n" ],
			[ 'exit_code' => 0, 'stdout' => "Current value: AUTO_DETECT_IP\n" ],
			[ 'exit_code' => 0, 'stdout' => "Current value: N\n" ],
		] );
		$manager = new CrossSitePairManager( $runner );

		$snapshot = $this->invokePrivate( $manager, 'publicSlaveSnapshot', [ $root ] );

		$this->assertSame( [
			'display_plugin_badge' => 'disabled',
			'visitor_address_source' => 'AUTO_DETECT_IP',
			'enable_tracking' => 'N',
		], $snapshot[ 'options' ] );
		$this->findProcessCommandContaining( $runner, 'shield opt-get --key=enable_tracking' );
		foreach ( $runner->calls as $call ) {
			$this->assertStringNotContainsString( 'export-options', \implode( ' ', $call[ 'command' ] ) );
		}
	}

	public function testNativePublicUpdateRequiresUpdatedOldAndCheckoutVersions() :void {
		$manager = new CrossSitePairManager();
		$metadata = new PublicUpgradePackageZipMetadata(
			'package.zip',
			'23.0.0',
			'wp-simple-firewall/icwp-wpsf.php'
		);
		$valid = [
			'status' => 'Updated',
			'old_version' => '22.1.3',
			'new_version' => '23.0.0',
		];

		$this->invokePrivate( $manager, 'assertPluginUpdateResult', [ $valid, $metadata ] );
		$this->addToAssertionCount( 1 );
		foreach ( [
			[ 'status' => 'Skipped' ],
			[ 'old_version' => '22.1.2' ],
			[ 'new_version' => '23.0.1' ],
		] as $change ) {
			try {
				$this->invokePrivate( $manager, 'assertPluginUpdateResult', [ \array_merge( $valid, $change ), $metadata ] );
				$this->fail( 'Expected native update result rejection.' );
			}
			catch ( \RuntimeException $exception ) {
				$this->assertSame(
					'Native Shield plugin update result did not match the public-to-checkout contract.',
					$exception->getMessage()
				);
			}
		}
	}

	public function testUpdateProviderConfiguresBothSitesWithOnePublishedArtifactIdentity() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-update-identity-' );
		$zip = $root.'/tmp/cross-site-test-lane/archive-workspace/current.zip';
		\mkdir( \dirname( $zip ), 0777, true );
		\file_put_contents( $zip, 'shared-checkout-archive' );
		$sha256 = \hash_file( 'sha256', $zip );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0, 'stdout' => $sha256.'  /var/www/html/wp-content/uploads/shield-cross-site-upgrade/wp-simple-firewall-current.zip' ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => '{"ok":true}' ],
			[ 'exit_code' => 0, 'stdout' => '{"ok":true}' ],
		] );
		$manager = new CrossSitePairManager( $runner );
		$metadata = new PublicUpgradePackageZipMetadata(
			$zip,
			'23.0.0',
			'wp-simple-firewall/icwp-wpsf.php'
		);

		$this->invokePrivate( $manager, 'configureUpdateProvider', [ $root, $metadata ] );

		$encodedConfigs = [];
		foreach ( $runner->calls as $call ) {
			$command = $call[ 'command' ];
			$fixtureIndex = \array_search( '/app/tests/fixtures/upgrade-public/write-update-config.php', $command, true );
			if ( $fixtureIndex !== false ) {
				$encodedConfigs[] = (string)( $command[ $fixtureIndex + 1 ] ?? '' );
			}
		}
		$this->assertCount( 2, $encodedConfigs );
		$this->assertSame( $encodedConfigs[ 0 ], $encodedConfigs[ 1 ] );
		$config = \json_decode( (string)\base64_decode( $encodedConfigs[ 0 ], true ), true );
		$this->assertSame( $sha256, $config[ 'package_sha256' ] ?? null );
		$this->assertSame(
			self::MASTER_INTERNAL_URL.'/wp-content/uploads/shield-cross-site-upgrade/wp-simple-firewall-current.zip',
			$config[ 'package' ] ?? null
		);
		$this->assertSame(
			[
				'package_url' => $config[ 'package' ],
				'sha256' => $sha256,
			],
			$manager->lastDiagnostics()[ 'public_upgrade_artifact_identity' ][ 'configured_sites' ][ 'master' ]
		);
		$this->assertSame(
			$manager->lastDiagnostics()[ 'public_upgrade_artifact_identity' ][ 'configured_sites' ][ 'master' ],
			$manager->lastDiagnostics()[ 'public_upgrade_artifact_identity' ][ 'configured_sites' ][ 'slave' ]
		);
	}

	public function testPublicCronSyncRunsOnlyDiscoveredScheduledHooks() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-public-cron-' );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => '[{"hook":"icwp-wpsf-importexport_notify"}]' ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => '[{"hook":"icwp-wpsf-importexport_updatenotified"}]' ],
			[ 'exit_code' => 0 ],
		] );
		$manager = new CrossSitePairManager( $runner );

		$this->invokePrivate( $manager, 'runPublicCronSync', [ $root ] );

		$this->assertCount( 5, $runner->calls );
		$this->findProcessCommandContaining( $runner, 'cron event schedule icwp-wpsf-importexport_notify now' );
		$this->findProcessCommandContaining( $runner, 'cron event list --fields=hook --format=json' );
		$this->findProcessCommandContaining( $runner, 'cron event run icwp-wpsf-importexport_notify' );
		$this->findProcessCommandContaining( $runner, 'cron event run icwp-wpsf-importexport_updatenotified' );
		foreach ( $runner->calls as $call ) {
			$command = \implode( ' ', $call[ 'command' ] );
			$this->assertStringNotContainsString( ' eval ', ' '.$command.' ' );
			$this->assertStringNotContainsString( 'eval-file', $command );
		}
	}

	public function testUpgradeMetadataRejectsPublicOrUnexpectedArtifact() :void {
		$manager = new CrossSitePairManager();

		foreach ( [
			new PublicUpgradePackageZipMetadata( 'package.zip', '22.1.3', 'wp-simple-firewall/icwp-wpsf.php' ),
			new PublicUpgradePackageZipMetadata( 'package.zip', '99.0.0', 'other/plugin.php' ),
		] as $metadata ) {
			try {
				$this->invokePrivate( $manager, 'assertUpgradePackageMetadata', [ $metadata ] );
				$this->fail( 'Expected package metadata rejection.' );
			}
			catch ( \RuntimeException $exception ) {
				$this->assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	public function testPublicMigrationSnapshotRequiresExpectedSemanticContract() :void {
		$manager = new CrossSitePairManager();
		$valid = [
			'migration_completed' => true,
			'root_xfer_excluded' => [ 'enable_tracking' ],
			'profileable_keys' => [ 'display_plugin_badge', 'enable_tracking', 'visitor_address_source' ],
			'profileable_options' => [
				'display_plugin_badge' => 'light',
				'enable_tracking' => 'Y',
				'visitor_address_source' => 'REMOTE_ADDR',
			],
			'default_profile_ids' => [ 7 ],
			'profiles' => [ [
				'id' => 7,
				'slug' => 'default',
				'label' => 'Default',
				'is_default' => true,
				'options' => [
					'display_plugin_badge' => 'light',
					'visitor_address_source' => 'REMOTE_ADDR',
					'enable_tracking' => 'Y',
				],
				'excluded' => [ 'enable_tracking' ],
				'non_profileable_option_keys' => [],
			] ],
			'active_registry' => [ [
				'url' => self::SLAVE_INTERNAL_URL,
				'import_id' => 'public-slave-id',
				'source' => 'legacy_option',
				'profile_ref' => 7,
				'profile_resolves_to_default' => true,
			] ],
			'master_sync_enabled' => 'Y',
			'master_sync_urls' => [ self::SLAVE_INTERNAL_URL ],
		];

		$this->invokePrivate( $manager, 'assertPublicMigrationState', [ $valid ] );
		$this->addToAssertionCount( 1 );
		$missingCatalog = $valid;
		unset( $missingCatalog[ 'profileable_keys' ] );
		try {
			$this->invokePrivate( $manager, 'assertPublicMigrationState', [ $missingCatalog ] );
			$this->fail( 'Expected incomplete migration snapshot rejection.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'profileable option catalog', $exception->getMessage() );
		}
		$invalidProfileValues = $valid;
		$invalidProfileValues[ 'profileable_options' ][ 'enable_tracking' ] = 'N';
		try {
			$this->invokePrivate( $manager, 'assertPublicMigrationState', [ $invalidProfileValues ] );
			$this->fail( 'Expected incomplete profile migration rejection.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'default profile', $exception->getMessage() );
		}
	}

	public function testCrossSiteComposeDefinesTrustedSyncHostAliases() :void {
		$manager = new CrossSitePairManager();
		$content = $this->readProjectFile( 'tests/docker/docker-compose.cross-site.yml' );
		$this->assertSame( self::MASTER_INTERNAL_URL, $manager->masterInternalUrl() );
		$this->assertSame( self::SLAVE_INTERNAL_URL, $manager->slaveInternalUrl() );

		foreach ( [
			'wordpress-master' => $manager->masterInternalUrl(),
			'wordpress-slave' => $manager->slaveInternalUrl(),
		] as $service => $url ) {
			$host = (string)\parse_url( $url, \PHP_URL_HOST );
			$this->assertNotEmpty( $host, $service );
			$this->assertMatchesRegularExpression(
				'/networks:\R\s+default:\R\s+aliases:\R\s+- '.\preg_quote( $host, '/' ).'(?:\R|$)/',
				$this->composeServiceBlock( $content, $service ),
				$service
			);
		}
	}

	public function testCrossSiteComposeMountsPluginVolumesAtParentDirectory() :void {
		$content = $this->readProjectFile( 'tests/docker/docker-compose.cross-site.yml' );
		foreach ( [ 'master', 'slave' ] as $site ) {
			$this->assertSame(
				2,
				\substr_count(
					$content,
					'cross-site-'.$site.'-plugin:/var/www/html/wp-content/plugins'
				)
			);
		}
		$this->assertStringNotContainsString(
			'/var/www/html/wp-content/plugins/wp-simple-firewall',
			$content
		);
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
		$this->assertContains( '--user', $command );
		$this->assertContains( 'root', $command );
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
				[ 'enable_tracking' ],
			]
		);

		$this->assertSame(
			[
				'importexport_masterurl',
				'global_enable_plugin_features',
				'importexport_enable',
				'enable_tracking',
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

		$result = $manager->waitForSlaveImportCompletion( $root );

		$this->assertSame( 'idle', $result[ 'rows' ][ 0 ][ 'queue_status' ] );
	}

	public function testSlaveImportWaitFailsWhenScheduledImportEventIsMissing() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-wait-direct-' );
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [
				$this->helperSuccessProcess( $this->waitingExportQueueState() ),
				$this->helperSuccessProcess( $this->slaveCronState( false, true ) ),
			] )
		);

		try {
			$manager->waitForSlaveImportCompletion( $root );
			$this->fail( 'Expected missing scheduled import event failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame(
				'Slave import event was not scheduled after the master export became ready.',
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

	public function testPostNotifyDispatchQueueStateAllowsSameSecondNotifyAndExportTimestamps() :void {
		$manager = new CrossSitePairManager();
		$state = $this->waitingExportQueueState();
		$state[ 'rows' ][ 0 ][ 'last_export_success_at' ] = $state[ 'rows' ][ 0 ][ 'last_ping_success_at' ];

		$this->expectNotToPerformAssertions();
		$this->invokePrivate( $manager, 'assertPostNotifyDispatchQueueState', [ $state ] );
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

	public function testPublicQueueTransitionAllowsOnlyNamedLifecycleFields() :void {
		$manager = new CrossSitePairManager();
		$before = $this->waitingExportQueueState();
		$after = $this->postExportQueueState();

		$this->invokePrivate( $manager, 'assertPublicQueueTransition', [ $before, $after ] );

		$after[ 'rows' ][ 0 ][ 'priority' ] = 100;
		$this->assertPublicQueueTransitionRejected( $manager, $before, $after );
	}

	public function testPublicQueueTransitionRequiresValidExportServedMarker() :void {
		$manager = new CrossSitePairManager();

		foreach ( [
			static function( array $before, array $after ) :array {
				$before[ 'rows' ][ 0 ][ 'meta' ][ 'export_served_at' ] = 1;
				return [ $before, $after ];
			},
			static function( array $before, array $after ) :array {
				unset( $after[ 'rows' ][ 0 ][ 'meta' ][ 'export_served_at' ] );
				return [ $before, $after ];
			},
			static function( array $before, array $after ) :array {
				$after[ 'rows' ][ 0 ][ 'meta' ][ 'export_served_at' ] = 0;
				return [ $before, $after ];
			},
			static function( array $before, array $after ) :array {
				$after[ 'rows' ][ 0 ][ 'meta' ][ 'export_served_at' ] = '30';
				return [ $before, $after ];
			},
		] as $mutate ) {
			$before = $this->waitingExportQueueState();
			$after = $this->postExportQueueState();
			[ $before, $after ] = $mutate( $before, $after );

			$this->assertPublicQueueTransitionRejected( $manager, $before, $after );
		}
	}

	public function testPublicQueueTransitionRejectsResidualMetadataChanges() :void {
		$manager = new CrossSitePairManager();

		foreach ( [
			static function( array $after ) :array {
				$after[ 'rows' ][ 0 ][ 'meta' ][ 'connection' ][ 'shared_secret' ] = 'changed-secret';
				return $after;
			},
			static function( array $after ) :array {
				$after[ 'rows' ][ 0 ][ 'meta' ][ 'unexpected' ] = 'unexpected-value';
				return $after;
			},
		] as $mutate ) {
			$before = $this->waitingExportQueueState();
			$after = $this->postExportQueueState();
			$after = $mutate( $after );

			$this->assertPublicQueueTransitionRejected( $manager, $before, $after );
		}
	}

	public function testCurrentScenarioDoesNotStartWhenPublicInventoryRemovalFails() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-current-boundary-' );
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = new CrossSitePairManager(
			new RecordingProcessRunner( [ 1 ] ),
			null,
			null,
			$refresher
		);

		try {
			$manager->prepareCurrentRuntimeScenario( $root );
			$this->fail( 'Expected public inventory cleanup failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'owned artifact inventory', $exception->getMessage() );
		}

		$this->assertSame( [], $refresher->refreshCalls );
	}

	public function testCurrentScenarioRestoresAutomaticCronBlockerBeforeScheduledImport() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-current-runtime-ready-' );
		$runner = RecordingProcessRunner::strict( \array_merge(
			\array_fill( 0, 21, [ 'exit_code' => 0 ] ),
			[
				$this->helperSuccessProcess( $this->waitingExportQueueState() ),
				$this->helperSuccessProcess( $this->slaveCronState( true, true ) ),
				[ 'exit_code' => 0 ],
				$this->helperSuccessProcess( $this->postExportQueueState() ),
			]
		) );
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = new CrossSitePairManager( $runner, null, null, $refresher );

		$manager->prepareCurrentRuntimeScenario( $root );
		$readinessCalls = \array_keys( \array_filter(
			$runner->calls,
			fn( array $call ) :bool => $this->isInternalHttpReadinessCall( $call )
		) );
		$blockerInstallCalls = \array_keys( \array_filter(
			$runner->calls,
			static fn( array $call ) :bool => \str_contains(
				(string)\end( $call[ 'command' ] ),
				'/app/tests/fixtures/cross-site/block-automatic-cron.php'
			)
		) );
		$this->assertCount( 2, $readinessCalls );
		$this->assertCount( 2, $blockerInstallCalls );
		$this->assertGreaterThan( \max( $readinessCalls ), \min( $blockerInstallCalls ) );
		$result = $manager->waitForSlaveImportCompletion( $root );

		$this->assertSame( [
			'wordpress-master-container',
			'wordpress-slave-container',
		], \array_column( $refresher->refreshCalls, 'container_id' ) );
		$slaveRows = \array_values( \array_filter(
			(array)( $result[ 'rows' ] ?? [] ),
			static fn( array $row ) :bool => ( $row[ 'url' ] ?? null ) === self::SLAVE_INTERNAL_URL
		) );
		$this->assertCount( 1, $slaveRows );
		$this->assertSame( 'idle', $slaveRows[ 0 ][ 'queue_status' ] ?? null );
	}

	public function testFinalCleanupRemovesArchiveWorkspaceAndCompletesBaselineChecks() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-final-cleanup-' );
		$workspace = Path::join( $root, 'tmp', 'cross-site-test-lane', 'archive-workspace' );
		\mkdir( $workspace, 0777, true );
		\file_put_contents( Path::join( $workspace, 'checkout.zip' ), 'fixture' );
		$manager = new CrossSitePairManager( new RecordingProcessRunner() );

		$manager->cleanupRun( $root );

		$this->assertDirectoryDoesNotExist( $workspace );
	}

	public function testFinalCleanupAggregatesArtifactAndSchemaAbsenceFailures() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-final-cleanup-failures-' );
		$runner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 1, 'stderr' => 'artifact removal failed' ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0 ],
			[ 'exit_code' => 0, 'stdout' => "shield_cross_site_master\n" ],
		] );
		$manager = new CrossSitePairManager( $runner );

		try {
			$manager->cleanupRun( $root );
			$this->fail( 'Expected aggregate final cleanup failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'Failed to remove and prove the owned artifact inventory', $exception->getMessage() );
			$this->assertStringContainsString( 'Owned cross-site databases remain after cleanup', $exception->getMessage() );
		}

	}

	public function testPrepareSuppressesSubprocessOutputByDefault() :void {
		$root = $this->createCrossSiteProjectRoot();
		$runner = new CrossSitePrepareProcessRunner();
		$docker = new RecordingDockerComposeExecutor();
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = $this->buildPairManagerForPrepareContract( $runner, $docker, $refresher );

		$this->runPrepareQuietly( $manager, $root, false );

		$this->assertNotEmpty( $docker->calls );
		$this->assertSame( [ 'up', '-d', '--wait', '--wait-timeout', '60', 'db' ], $docker->calls[ 0 ][ 'sub_command' ] );
		$composeEnv = $this->assertHasEnvOverrides( $docker->calls[ 0 ] );
		$this->assertCrossSiteReusableLabelEnv( $composeEnv );
		foreach ( $docker->calls as $call ) {
			$this->assertTrue( $call[ 'has_output_callback' ] );
			$this->assertFalse( $call[ 'show_docker_output' ] );
			$this->assertSameDockerLabelEnvironment( $composeEnv, $this->assertHasEnvOverrides( $call ) );
		}
		$this->assertSame( [], $runner->calls );
		$this->assertSame( [], $refresher->refreshCalls );
	}

	public function testPrepareShowsSetupOutputOnlyWhenExplicitlyRequested() :void {
		$root = $this->createCrossSiteProjectRoot();
		$runner = new RecordingProcessRunner();
		$docker = new RecordingDockerComposeExecutor();
		$refresher = new CrossSiteRuntimeRefresherRecorder();
		$manager = $this->buildPairManagerForPrepareContract( $runner, $docker, $refresher );

		$this->runPrepareQuietly( $manager, $root, true );

		$this->assertNotEmpty( $docker->calls );
		foreach ( $docker->calls as $call ) {
			$this->assertFalse( $call[ 'has_output_callback' ] );
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
		$this->assertSame( [], $runner->calls );
		$this->assertSame( [], $refresher->refreshCalls );
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
			$this->runPrepareQuietly( $manager, $root, false );
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

	private function assertPublicQueueTransitionRejected(
		CrossSitePairManager $manager,
		array $before,
		array $after
	) :void {
		try {
			$this->invokePrivate( $manager, 'assertPublicQueueTransition', [ $before, $after ] );
			$this->fail( 'Expected public queue transition rejection.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->addToAssertionCount( 1 );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function waitingExportQueueState() :array {
		return [
			'rows' => [
				[
					'id' => 27,
					'url' => self::SLAVE_INTERNAL_URL,
					'url_hash' => '4ed9b9677524f885836f6b9ccbf0ea65',
					'import_id' => 'public-slave-import-id',
					'profile_ref' => 7,
					'source' => 'legacy_option',
					'status' => 'active',
					'priority' => 10,
					'queue_status' => 'waiting_export',
					'queued_at' => 5,
					'picked_at' => 1,
					'lock_until' => 20,
					'next_ping_at' => 10,
					'expected_export_by' => 15,
					'last_ping_attempt_at' => 10,
					'last_ping_success_at' => 10,
					'last_ping_failure_at' => 0,
					'last_ping_http_code' => 200,
					'last_ping_error' => '',
					'last_export_request_at' => 0,
					'last_export_success_at' => 5,
					'last_export_result_code' => 'success',
					'last_export_failure_at' => 0,
					'last_export_error' => '',
					'ping_attempts_total' => 1,
					'consecutive_failures' => 0,
					'meta' => [
						'connection' => [
							'shared_secret' => 'stable-secret',
						],
						'legacy' => [
							'option_key' => 'importexport_enable',
						],
					],
					'created_at' => 1,
					'updated_at' => 20,
					'deleted_at' => 0,
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
					'url' => self::SLAVE_INTERNAL_URL,
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
		$state = $this->waitingExportQueueState();
		$state[ 'rows' ][ 0 ] = \array_merge( $state[ 'rows' ][ 0 ], [
			'queue_status' => 'idle',
			'next_ping_at' => 86430,
			'last_ping_attempt_at' => 20,
			'last_export_request_at' => 20,
			'last_export_success_at' => 30,
			'expected_export_by' => 0,
			'lock_until' => 0,
			'picked_at' => 0,
			'updated_at' => 30,
		] );
		$state[ 'rows' ][ 0 ][ 'meta' ][ 'export_served_at' ] = 30;

		return $state;
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
			'master_url' => self::MASTER_INTERNAL_URL,
			'import_id' => 'slave-import-id',
		];
	}

	private function readProjectFile( string $relativePath ) :string {
		$path = \dirname( __DIR__, 2 ).'/'.$relativePath;
		$this->assertFileExists( $path );

		return (string)\file_get_contents( $path );
	}

	private function composeServiceBlock( string $content, string $service ) :string {
		$pattern = \sprintf(
			'/^  %s:\R(?<block>(?:    .*(?:\R|$))*)/m',
			\preg_quote( $service, '/' )
		);
		$this->assertSame( 1, \preg_match( $pattern, $content, $matches ) );
		return (string)( $matches[ 'block' ] ?? '' );
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
		bool $showSetupOutput
	) :void {
		\ob_start();
		try {
			$manager->prepare( $root, $showSetupOutput );
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

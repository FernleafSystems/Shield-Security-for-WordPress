<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record as ResultItemRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\General;

class AfsUpgradeQueueingTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_process_complete_schedules_plugin_and_theme_cleanup_crons_without_scanning() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );
		$afs = new Afs();

		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [
				'akismet/akismet.php',
				'hello-dolly/hello.php',
			],
		] );
		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action' => 'update',
			'type'   => 'theme',
			'themes' => [
				'twentytwentyfour',
			],
		] );

		$this->assertSame( [], $scans->queuedAssets );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php', 0 ],
			[ 'plugin', 'hello-dolly/hello.php', 0 ],
			[ 'theme', 'twentytwentyfour', 0 ],
		], \array_column( $scheduled, 'args' ) );
		$this->assertSame( [
			'icwp-wpsf-afs_asset_change_cleanup',
			'icwp-wpsf-afs_asset_change_cleanup',
			'icwp-wpsf-afs_asset_change_cleanup',
		], \array_column( $scheduled, 'hook' ) );
		$this->assertSame( [ 1700000060, 1700000060, 1700000060 ], \array_column( $scheduled, 'timestamp' ) );
	}

	public function test_post_install_schedules_cleanup_crons_without_shutdown_scan_queueing() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );
		$afs = new Afs();
		$response = (object)[ 'destination' => 'asset-installed' ];

		$result = $afs->queueAssetScansFromUpgraderPostInstall( $response, [
			'plugin' => 'akismet/akismet.php',
			'theme'  => 'twentytwentyfour',
		] );

		$this->assertSame( $response, $result );
		$this->assertSame( [], $scans->queuedAssets );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php', 0 ],
			[ 'theme', 'twentytwentyfour', 0 ],
		], \array_column( $scheduled, 'args' ) );
	}

	public function test_core_update_schedules_core_cleanup_cron() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );

		( new Afs() )->queueCoreAssetScan( '6.7.1' );

		$this->assertSame( [], $scans->queuedAssets );
		$this->assertSame( [
			[ 'core', 'core', 0 ],
		], \array_column( $scheduled, 'args' ) );
	}

	public function test_theme_delete_hook_does_not_schedule_when_theme_was_not_deleted() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$scans = new AfsUpgradeQueueingRecordingScans();
		$this->installController( $scans );

		( new Afs() )->queueThemeAssetScan( 'twentytwentyfour', false );

		$this->assertSame( [], $scheduled );
		$this->assertSame( [], $scans->queuedAssets );
	}

	public function test_run_registers_asset_change_cleanup_and_core_update_hooks() :void {
		$actions = [];
		$filters = [];
		$this->setHashesStorageDir( $this->makeTempDir( 'hashes' ) );
		Functions\when( 'is_main_network' )->justReturn( false );
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook, array $args = [] ) :bool {
				unset( $hook, $args );
				return false;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$actions ) :bool {
				$actions[] = [
					'hook'          => $hook,
					'callback'      => $callback,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$filters ) :bool {
				$filters[] = [
					'hook'          => $hook,
					'callback'      => $callback,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);
		$this->installController( new AfsUpgradeQueueingRecordingScans() );
		ServicesState::mergeItems( [
			'service_wpgeneral' => new AfsUpgradeQueueingGeneral(),
		] );

		( new AfsUpgradeQueueingRunTestDouble() )->exposeRun();

		$cleanupActions = \array_values( \array_filter(
			$actions,
			static fn( array $action ) :bool => $action[ 'hook' ] === 'icwp-wpsf-afs_asset_change_cleanup'
		) );
		$coreUpdatedActions = \array_values( \array_filter(
			$actions,
			static fn( array $action ) :bool => $action[ 'hook' ] === '_core_updated_successfully'
		) );
		$this->assertCount( 1, $cleanupActions );
		$this->assertSame( 3, $cleanupActions[ 0 ][ 'accepted_args' ] );
		$this->assertCount( 1, $coreUpdatedActions );
		$this->assertSame( 1, $coreUpdatedActions[ 0 ][ 'accepted_args' ] );
		$this->assertSame( [ 'upgrader_post_install' ], \array_column( $filters, 'hook' ) );
	}

	public function test_core_build_scan_result_records_wordpress_asset_version() :void {
		$this->installController(
			new AfsUpgradeQueueingRecordingScans(),
			new AfsUpgradeQueueingResultItemsDb()
		);
		ServicesState::mergeItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000600 ),
			'service_wpgeneral' => new AfsUpgradeQueueingGeneral( '6.7.2' ),
		] );

		$record = ( new Afs() )->buildScanResult( [
			'path_fragment' => 'wp-admin/includes/file.php',
			'is_in_core'    => true,
		] );

		$this->assertSame( 'core', $record->asset_type );
		$this->assertSame( 'core', $record->asset_key );
		$this->assertSame( '6.7.2', $record->meta[ 'asset_version' ] );
	}

	private function installController( AfsUpgradeQueueingRecordingScans $scans, ?AfsUpgradeQueueingResultItemsDb $resultItemsDb = null ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->comps = (object)[
			'scans' => $scans,
		];
		if ( $resultItemsDb instanceof AfsUpgradeQueueingResultItemsDb ) {
			$controller->db_con = (object)[
				'scan_result_items' => $resultItemsDb,
			];
		}

		PluginControllerInstaller::install( $controller );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
		] );
	}

	private function installCronMocks( array &$scheduled ) :void {
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook, array $args = [] ) :bool {
				unset( $hook, $args );
				return false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( int $timestamp, string $hook, array $args = [] ) use ( &$scheduled ) :bool {
				$scheduled[] = [
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
				];
				return true;
			}
		);
	}

	private function setHashesStorageDir( string $dir ) :void {
		$property = ( new \ReflectionClass( HashesStorageDir::class ) )->getProperty( 'dir' );
		$property->setAccessible( true );
		$property->setValue( null, $dir );
	}

	private function resetHashesStorageDir() :void {
		$property = ( new \ReflectionClass( HashesStorageDir::class ) )->getProperty( 'dir' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = \str_replace( '\\', '/', \sys_get_temp_dir().'/shield-afs-run-'.$suffix.'-'.\uniqid() );
		@mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}
		@rmdir( $dir );
	}
}

class AfsUpgradeQueueingRecordingScans {

	public array $queuedAssets = [];

	public function startAfsAssetScan( string $assetType, string $assetKey, bool $resetIgnored = false ) :bool {
		unset( $resetIgnored );
		$this->queuedAssets[] = [ $assetType, $assetKey ];
		return true;
	}
}

class AfsUpgradeQueueingRunTestDouble extends Afs {

	public function exposeRun() :void {
		$this->run();
	}
}

class AfsUpgradeQueueingResultItemsDb {

	public function getRecord() :ResultItemRecord {
		return new ResultItemRecord();
	}
}

class AfsUpgradeQueueingGeneral extends General {

	private string $version;

	public function __construct( string $version = '6.7.1' ) {
		$this->version = $version;
	}

	public function isCron() :bool {
		return false;
	}

	public function getVersion( $ignoreClassicpress = false ) :string {
		unset( $ignoreClassicpress );
		return $this->version;
	}
}

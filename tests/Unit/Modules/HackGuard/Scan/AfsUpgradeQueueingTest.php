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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Fs;
use FernleafSystems\Wordpress\Services\Core\General;

class AfsUpgradeQueueingTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_process_complete_delegates_plugin_and_theme_assets_without_scanning() :void {
		$scans = new AfsUpgradeQueueingRecordingScans();
		$coordinator = $this->installController( $scans );
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
			[ 'plugin', 'akismet/akismet.php', 60 ],
			[ 'plugin', 'hello-dolly/hello.php', 60 ],
			[ 'theme', 'twentytwentyfour', 60 ],
		], $coordinator->assets );
	}

	public function test_post_install_delegates_assets_without_shutdown_scan_queueing() :void {
		$scans = new AfsUpgradeQueueingRecordingScans();
		$coordinator = $this->installController( $scans );
		$afs = new Afs();
		$response = (object)[ 'destination' => 'asset-installed' ];

		$result = $afs->queueAssetScansFromUpgraderPostInstall( $response, [
			'plugin' => 'akismet/akismet.php',
			'theme'  => 'twentytwentyfour',
		] );

		$this->assertSame( $response, $result );
		$this->assertSame( [], $scans->queuedAssets );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php', 60 ],
			[ 'theme', 'twentytwentyfour', 60 ],
		], $coordinator->assets );
	}

	public function test_core_update_delegates_core_asset() :void {
		$scans = new AfsUpgradeQueueingRecordingScans();
		$coordinator = $this->installController( $scans );

		( new Afs() )->queueCoreAssetScan( '6.7.1' );

		$this->assertSame( [], $scans->queuedAssets );
		$this->assertSame( [
			[ 'core', 'core', 60 ],
		], $coordinator->assets );
	}

	public function test_theme_delete_hook_does_not_delegate_when_theme_was_not_deleted() :void {
		$scans = new AfsUpgradeQueueingRecordingScans();
		$coordinator = $this->installController( $scans );

		( new Afs() )->queueThemeAssetScan( 'twentytwentyfour', false );

		$this->assertSame( [], $coordinator->assets );
		$this->assertSame( [], $scans->queuedAssets );
	}

	public function test_theme_delete_hook_uses_success_default_when_argument_is_omitted() :void {
		$coordinator = $this->installController( new AfsUpgradeQueueingRecordingScans() );

		( new Afs() )->queueThemeAssetScan( 'twentytwentyfour' );

		$this->assertSame( [
			[ 'theme', 'twentytwentyfour', 60 ],
		], $coordinator->assets );
	}

	public function test_upgrader_adapters_ignore_hostile_members_and_preserve_valid_siblings() :void {
		$coordinator = $this->installController( new AfsUpgradeQueueingRecordingScans() );
		$afs = new Afs();

		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [
				' akismet/akismet.php ',
				'',
				'  ',
				'0',
				' 0 ',
				123,
				1.5,
				[],
				(object)[],
				null,
				false,
			],
		] );
		$response = (object)[ 'ok' => true ];
		$this->assertSame( $response, $afs->queueAssetScansFromUpgraderPostInstall( $response, [
			'theme'  => ' twentytwentyfour ',
			'plugin' => [],
		] ) );

		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php', 60 ],
			[ 'theme', 'twentytwentyfour', 60 ],
		], $coordinator->assets );
		$afs->queueAssetScansFromUpgraderPostInstall( $response, [
			'plugin' => '0',
			'theme'  => ' 0 ',
		] );

		$afs->queueAssetScansFromUpgraderProcessComplete( null, null );
		$afs->queueAssetScansFromUpgraderProcessComplete( null, [ 'action' => [], 'type' => 'plugin' ] );
		$afs->queueAssetScansFromUpgraderProcessComplete( null, [
			'action' => 'update',
			'type' => 'plugin',
			'plugins' => (object)[],
		] );
		$this->assertCount( 2, $coordinator->assets );
	}

	public function test_delete_hook_adapters_ignore_hostile_values() :void {
		$coordinator = $this->installController( new AfsUpgradeQueueingRecordingScans() );
		$afs = new Afs();

		foreach ( [ null, false, 123, 1.5, [], (object)[], '', '  ', '0', ' 0 ' ] as $invalid ) {
			$afs->queuePluginAssetScan( $invalid );
			$afs->queueThemeAssetScan( $invalid, true );
		}
		$afs->queueThemeAssetScan( 'twentytwentyfour', 'truthy' );

		$this->assertSame( [], $coordinator->assets );
	}

	public function test_run_does_not_register_asset_lifecycle_or_cleanup_hooks() :void {
		$actions = [];
		$filters = [];
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

		$this->assertSame( [], \array_intersect( [
			'icwp-wpsf-afs_asset_change_cleanup',
			'_core_updated_successfully',
			'deleted_plugin',
			'deleted_theme',
			'upgrader_process_complete',
		], \array_column( $actions, 'hook' ) ) );
		$this->assertNotContains( 'upgrader_post_install', \array_column( $filters, 'hook' ) );
	}

	public function test_wpv_run_retains_executor_hook_without_asset_lifecycle_ownership() :void {
		$actions = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$actions ) :bool {
				unset( $callback, $priority, $acceptedArgs );
				$actions[] = $hook;
				return true;
			}
		);
		$this->installController( new AfsUpgradeQueueingRecordingScans() );

		( new AfsUpgradeQueueingWpvRunTestDouble() )->exposeRun();

		$this->assertContains( 'icwp-wpsf-ondemand_scan_wpv', $actions );
		$this->assertContains( 'load-plugins.php', $actions );
		$this->assertSame( [], \array_intersect( [
			'upgrader_process_complete',
			'deleted_plugin',
		], $actions ) );
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

	private function installController( AfsUpgradeQueueingRecordingScans $scans, ?AfsUpgradeQueueingResultItemsDb $resultItemsDb = null ) :AfsUpgradeQueueingCoordinator {
		$coordinator = new AfsUpgradeQueueingCoordinator();
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->comps = (object)[
			'asset_coordinator' => $coordinator,
			'scans'             => $scans,
		];
		if ( $resultItemsDb instanceof AfsUpgradeQueueingResultItemsDb ) {
			$controller->db_con = (object)[
				'scan_result_items' => $resultItemsDb,
			];
		}

		PluginControllerInstaller::install( $controller );
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpfs'    => new Fs(),
		] );
		return $coordinator;
	}

}

class AfsUpgradeQueueingCoordinator {

	public array $assets = [];

	public function enqueueAsset( string $assetType, string $assetKey, int $delay ) :bool {
		$this->assets[] = [ $assetType, $assetKey, $delay ];
		return true;
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

class AfsUpgradeQueueingWpvRunTestDouble extends Wpv {

	public function exposeRun() :void {
		$this->run();
	}

	public function getSlug() :string {
		return 'wpv';
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator;

class AssetCoordinatorTestLog {

	public static array $messages = [];
}

function error_log( string $message ) :bool {
	AssetCoordinatorTestLog::$messages[] = $message;
	return true;
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\AssetCoordinator;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotPlugins,
	SnapshotPluginVo
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestFs
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Services\Core\{
	CoreFileHashes,
	Cron,
	Plugins,
	Request,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class AssetCoordinatorTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $actions = [];
	private array $filters = [];
	private array $options = [];
	private array $scheduled = [];
	private array $unscheduled = [];
	private array $autoloadValues = [];
	private array $servicesSnapshot = [];
	private bool $persistWrites = true;
	private bool $updateResult = true;
	private bool $isMainNetwork = true;
	private bool $isMainSite = true;
	private AssetCoordinatorTestRequest $request;
	private AssetCoordinatorTestScans $scans;
	private AssetCoordinatorTestCron $cron;
	private Controller $controller;

	protected function setUp() :void {
		parent::setUp();
		$this->resetHashesStorageDir();
		$this->servicesSnapshot = ServicesState::snapshot();
		\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages = [];
		$this->request = new AssetCoordinatorTestRequest( 1700000000 );
		$this->scans = new AssetCoordinatorTestScans();
		$this->cron = new AssetCoordinatorTestCron( $this->scheduled );
		$this->registerWordPressFunctions();
		$this->installController();
		ServicesState::installItems( [
			'service_corefilehashes' => new AssetCoordinatorTestCoreHashes(),
			'service_request'        => $this->request,
			'service_wpcron'         => $this->cron,
			'service_wpplugins'      => new AssetCoordinatorTestPlugins(),
			'service_wpthemes'       => new AssetCoordinatorTestThemes(),
		] );
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_registers_exact_lifecycle_and_wakeup_hooks() :void {
		( new AssetCoordinator() )->execute();

		$this->assertHook( $this->filters, 'upgrader_post_install', 10, 2 );
		$this->assertHook( $this->actions, 'upgrader_process_complete', 10, 2 );
		$this->assertHook( $this->actions, '_core_updated_successfully', 10, 1 );
		$this->assertHook( $this->actions, 'deleted_plugin', 10, 2 );
		$this->assertHook( $this->actions, 'deleted_theme', 10, 2 );
		$this->assertHook( $this->actions, 'icwp-wpsf-hourly_cron', 10, 0 );
		$this->assertHook( $this->actions, 'shield/scan_queue_completed', 10, 0 );
		$this->assertHook( $this->actions, 'icwp-wpsf-asset_coordinator', 10, 1 );
		$this->assertNotHooked( $this->actions, 'icwp-wpsf-pre_plugin_shutdown' );
	}

	public function test_intake_coalesces_assets_and_wpv_and_returns_filter_response() :void {
		$coordinator = new AssetCoordinator();
		$response = (object)[ 'destination' => 'installed' ];

		$this->assertSame( $response, $coordinator->onUpgraderPostInstall( $response, [
			'plugin' => ' akismet/akismet.php ',
			'theme'  => ' twentytwentyfive ',
		] ) );
		$coordinator->onUpgraderProcessComplete( null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [ 'akismet/akismet.php', 'hello-dolly/hello.php' ],
		] );
		$coordinator->onUpgraderProcessComplete( null, [
			'action' => 'translation',
			'type'   => 'translation',
		] );
		$coordinator->onCoreUpdated( '6.8.2' );
		$coordinator->onDeletedPlugin( 'deleted/deleted.php', false );
		$coordinator->onDeletedPlugin( 'deleted/deleted.php', true );
		$coordinator->onDeletedTheme( 'failed-theme', false );
		$coordinator->onDeletedTheme( 'deleted-theme', true );

		$state = $this->state();
		$this->assertSame(
			[ 'akismet/akismet.php', 'hello-dolly/hello.php', 'deleted/deleted.php' ],
			\array_keys( $state[ 'assets' ][ 'plugin' ] )
		);
		$this->assertSame(
			[ 'twentytwentyfive', 'deleted-theme' ],
			\array_keys( $state[ 'assets' ][ 'theme' ] )
		);
		$this->assertSame( [ 'core' ], \array_keys( $state[ 'assets' ][ 'core' ] ) );
		$this->assertSame( [ 'attempts' => 0, 'due_at' => 1700000010 ], $state[ 'wpv' ] );
		$this->assertNotContains( 'failed-theme', \array_keys( $state[ 'assets' ][ 'theme' ] ) );
	}

	public function test_asset_failure_retries_three_times_while_wpv_succeeds_independently() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'deleted/deleted.php' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
				],
				'theme' => [],
				'core' => [],
			],
			'wpv' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
		];
		$this->scans->assetResult = false;
		$this->scans->wpvResult = StartScansResult::fromRequested( [ 'wpv' ] )->addResumed( 'wpv', 91 );
		$coordinator = new AssetCoordinator();

		$coordinator->runDueWork();
		$this->assertSame( [ 'attempts' => 1, 'due_at' => 1700000060 ], $this->assetRecord() );
		$this->assertArrayNotHasKey( 'wpv', $this->state() );
		$this->assertSame( [ [ 'plugin', 'deleted/deleted.php' ] ], $this->scans->assets );
		$this->assertSame( 1, $this->scans->wpvCalls );

		$this->request->timestamp = 1700000060;
		$coordinator->runDueWork();
		$this->request->timestamp = 1700000120;
		$coordinator->runDueWork();

		$this->assertSame( [ 'attempts' => 3, 'due_at' => 0 ], $this->assetRecord() );
		$this->assertCount( 1, \array_filter(
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages,
			static fn( string $message ) :bool => \str_contains( $message, 'exhausted plugin:deleted/deleted.php' )
		) );

		$coordinator->onDeletedPlugin( 'deleted/deleted.php', true );
		$this->assertSame( [ 'attempts' => 0, 'due_at' => 1700000180 ], $this->assetRecord() );
		$this->assertSame( [ 'attempts' => 0, 'due_at' => 1700000130 ], $this->state()[ 'wpv' ] );
	}

	public function test_mixed_enqueue_preserves_fresh_ordinary_work_and_earliest_due() :void {
		$coordinator = new AssetCoordinator();

		$this->assertFalse( $coordinator->enqueuePromotionFollowUp( 'core', 'core', '1.0.0' ) );
		$this->assertFalse( $coordinator->enqueuePromotionFollowUp( 'plugin', 'valid/plugin.php', "bad\0version" ) );
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', ' valid/plugin.php ', '1.0.0' ) );
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', 'valid/plugin.php', '2.0.0' ) );

		$this->assertSame( [ 'valid/plugin.php' ], \array_keys( $this->state()[ 'assets' ][ 'plugin' ] ) );
		$this->assertSame( [
			'attempts'                   => 0,
			'due_at'                     => 1700000060,
			'required_published_version' => '2.0.0',
		], $this->state()[ 'assets' ][ 'plugin' ]['valid/plugin.php'] );

		$this->assertTrue( $coordinator->enqueueAsset( 'plugin', 'valid/plugin.php', 120 ) );
		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000120,
		], $this->state()[ 'assets' ][ 'plugin' ]['valid/plugin.php'] );

		$this->options[ $this->optionKey() ][ 'assets' ][ 'plugin' ]['valid/plugin.php'] = [
			'attempts' => 2,
			'due_at'   => 1700000090,
		];
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', 'valid/plugin.php', '3.0.0' ) );
		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000060,
		], $this->state()[ 'assets' ][ 'plugin' ]['valid/plugin.php'] );

		$this->options[ $this->optionKey() ][ 'assets' ][ 'plugin' ]['valid/plugin.php'] = [
			'attempts' => 0,
			'due_at'   => 1700000030,
		];
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', 'valid/plugin.php', '4.0.0' ) );
		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000030,
		], $this->state()[ 'assets' ][ 'plugin' ]['valid/plugin.php'] );
	}

	public function test_non_runnable_ordinary_work_does_not_absorb_promotion() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'terminal/plugin.php' => [ 'attempts' => 3, 'due_at' => 0 ],
					'dormant/plugin.php'  => [ 'attempts' => 0, 'due_at' => 0 ],
				],
				'theme' => [],
				'core' => [],
			],
		];
		$coordinator = new AssetCoordinator();

		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', 'terminal/plugin.php', '1.0.0' ) );
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', 'dormant/plugin.php', '2.0.0' ) );

		$this->assertSame( [
			'attempts'                   => 0,
			'due_at'                     => 1700000060,
			'required_published_version' => '1.0.0',
		], $this->state()[ 'assets' ][ 'plugin' ]['terminal/plugin.php'] );
		$this->assertSame( [
			'attempts'                   => 0,
			'due_at'                     => 1700000060,
			'required_published_version' => '2.0.0',
		], $this->state()[ 'assets' ][ 'plugin' ]['dormant/plugin.php'] );
	}

	public function test_non_applicable_promotion_follow_up_is_consumed_without_starting_scan() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'missing/plugin.php' => [
						'attempts'                   => 0,
						'due_at'                     => 1700000000,
						'required_published_version' => '1.0.0',
					],
				],
				'theme' => [],
				'core' => [],
			],
		];

		( new AssetCoordinator() )->runDueWork();

		$this->assertSame( [], $this->state()[ 'assets' ][ 'plugin' ] );
		$this->assertSame( [], $this->scans->assets );
	}

	public function test_conditioned_scan_failure_retries_without_losing_version() :void {
		$asset = new SnapshotPluginVo( 'published/plugin.php', '1.2.3' );
		$this->installPublishedSnapshot( $asset );
		$coordinator = new AssetCoordinator();
		$this->scans->assetResult = false;
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', $asset->file, $asset->Version ) );

		$this->request->timestamp = 1700000060;
		$coordinator->runDueWork();
		$this->assertSame( [
			'attempts'                   => 1,
			'due_at'                     => 1700000120,
			'required_published_version' => '1.2.3',
		], $this->state()[ 'assets' ][ 'plugin' ][ $asset->file ] );

		$this->request->timestamp = 1700000120;
		$coordinator->runDueWork();
		$this->assertSame( [
			'attempts'                   => 2,
			'due_at'                     => 1700000180,
			'required_published_version' => '1.2.3',
		], $this->state()[ 'assets' ][ 'plugin' ][ $asset->file ] );
		$this->assertSame( [
			[ 'plugin', $asset->file ],
			[ 'plugin', $asset->file ],
		], $this->scans->assets );
	}

	/**
	 * @dataProvider provideScanResults
	 */
	public function test_later_ordinary_work_survives_selected_promotion_result( bool $scanResult ) :void {
		$asset = new SnapshotPluginVo( 'changed/plugin.php', '1.0.0' );
		$this->installPublishedSnapshot( $asset );
		$coordinator = new AssetCoordinator();
		$this->scans->assetResult = $scanResult;
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', $asset->file, $asset->Version ) );
		$this->scans->beforeStart = function ( string $assetType, string $assetKey ) use ( $coordinator ) :void {
			$this->scans->beforeStart = null;
			$this->assertTrue( $coordinator->enqueueAsset( $assetType, $assetKey, 120 ) );
		};

		$this->request->timestamp = 1700000060;
		$coordinator->runDueWork();

		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000180,
		], $this->state()[ 'assets' ][ 'plugin' ]['changed/plugin.php'] );
	}

	/**
	 * @dataProvider provideScanResults
	 */
	public function test_refreshed_ordinary_work_survives_selected_ordinary_result( bool $scanResult ) :void {
		$asset = new SnapshotPluginVo( 'changed/plugin.php', '2.0.0' );
		$this->installPublishedSnapshot( $asset );
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					$asset->file => [ 'attempts' => 1, 'due_at' => 1700000000 ],
				],
				'theme' => [],
				'core' => [],
			],
		];
		$coordinator = new AssetCoordinator();
		$this->scans->assetResult = $scanResult;
		$this->scans->beforeStart = function ( string $assetType, string $assetKey ) use ( $coordinator ) :void {
			$this->scans->beforeStart = null;
			$this->assertTrue( $coordinator->enqueuePromotionFollowUp( $assetType, $assetKey, '1.0.0' ) );
		};

		$coordinator->runDueWork();

		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000000,
		], $this->state()[ 'assets' ][ 'plugin' ][ $asset->file ] );
	}

	/**
	 * @dataProvider provideScanResults
	 */
	public function test_equivalent_ordinary_work_accepts_selected_result( bool $scanResult ) :void {
		$asset = new SnapshotPluginVo( 'changed/plugin.php', '2.0.0' );
		$this->installPublishedSnapshot( $asset );
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					$asset->file => [ 'attempts' => 0, 'due_at' => 1700000000 ],
				],
				'theme' => [],
				'core' => [],
			],
		];
		$coordinator = new AssetCoordinator();
		$this->scans->assetResult = $scanResult;
		$this->scans->beforeStart = function ( string $assetType, string $assetKey ) use ( $coordinator ) :void {
			$this->scans->beforeStart = null;
			$this->assertTrue( $coordinator->enqueuePromotionFollowUp( $assetType, $assetKey, '1.0.0' ) );
		};

		$coordinator->runDueWork();

		if ( $scanResult ) {
			$this->assertArrayNotHasKey( $asset->file, $this->state()[ 'assets' ][ 'plugin' ] );
		}
		else {
			$this->assertSame( [
				'attempts' => 1,
				'due_at'   => 1700000060,
			], $this->state()[ 'assets' ][ 'plugin' ][ $asset->file ] );
		}
	}

	public function test_stale_promotion_version_cannot_suppress_current_ordinary_scan() :void {
		$asset = new SnapshotPluginVo( 'changed/plugin.php', '2.0.0' );
		$this->installPublishedSnapshot( $asset );
		$coordinator = new AssetCoordinator();

		$this->assertTrue( $coordinator->enqueueAsset( 'plugin', $asset->file, 0 ) );
		$this->assertTrue( $coordinator->enqueuePromotionFollowUp( 'plugin', $asset->file, '1.0.0' ) );
		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => 1700000000,
		], $this->state()[ 'assets' ][ 'plugin' ][ $asset->file ] );

		$coordinator->runDueWork();

		$this->assertSame( [ [ 'plugin', $asset->file ] ], $this->scans->assets );
		$this->assertArrayNotHasKey( $asset->file, $this->state()[ 'assets' ][ 'plugin' ] );
	}

	public function provideScanResults() :array {
		return [
			'success' => [ true ],
			'failure' => [ false ],
		];
	}

	public function test_core_readiness_precedes_targeted_scan() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [],
				'theme'  => [],
				'core'   => [
					'core' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
				],
			],
		];
		$this->scans->assetResult = true;

		( new AssetCoordinator() )->runDueWork();

		$this->assertSame( [ [ 'core', 'core' ] ], $this->scans->assets );
		$this->assertSame( [], $this->state()[ 'assets' ][ 'core' ] );
	}

	public function test_failed_wpv_uses_fixed_retry_and_exhausts_after_three_attempts() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [],
				'theme'  => [],
				'core'   => [],
			],
			'wpv' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
		];
		$this->scans->wpvResult = StartScansResult::fromRequested( [ 'wpv' ] )
			->addFailure( 'wpv', StartScansResult::REASON_SCAN_UNAVAILABLE );
		$coordinator = new AssetCoordinator();

		$coordinator->runDueWork();
		$this->assertSame( [ 'attempts' => 1, 'due_at' => 1700000060 ], $this->state()[ 'wpv' ] );

		$this->request->timestamp = 1700000060;
		$coordinator->runDueWork();
		$this->request->timestamp = 1700000120;
		$coordinator->runDueWork();

		$this->assertSame( [ 'attempts' => 3, 'due_at' => 0 ], $this->state()[ 'wpv' ] );
		$this->assertCount( 1, \array_filter(
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages,
			static fn( string $message ) :bool => \str_contains( $message, 'exhausted wpv' )
		) );
	}

	public function test_hourly_discovery_coalesces_clears_and_later_rediscovers_missing_assets() :void {
		ServicesState::mergeItems( [
			'service_wpplugins' => new SnapshotPlugins( [
				new SnapshotPluginVo( 'missing-snapshot/plugin.php', '1.0.0' ),
			] ),
		] );
		$coordinator = new AssetCoordinator();

		$this->assertTrue( $coordinator->discoverMissingSnapshots() );

		$this->assertArrayHasKey(
			'build_missing_snapshots',
			$this->state(),
			\implode(
				"\n",
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages
			)
		);
		$this->assertTrue( $this->state()[ 'build_missing_snapshots' ] );
		$this->assertCount( 1, $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );

		$this->assertTrue( $coordinator->discoverMissingSnapshots() );
		$this->assertCount( 1, $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );

		$coordinator->runDueWork();
		$this->assertArrayNotHasKey( 'build_missing_snapshots', $this->state() );
		$this->assertSame( [], $this->scans->assets );
		$this->assertSame( 0, $this->scans->wpvCalls );

		$this->assertTrue( $coordinator->discoverMissingSnapshots() );
		$this->assertTrue( $this->state()[ 'build_missing_snapshots' ] );
	}

	/**
	 * @dataProvider provideNonOwnerTopologies
	 */
	public function test_non_owner_topologies_do_not_run_routine_work_but_preserve_targeted_and_wpv_work(
		bool $isMainNetwork,
		bool $isMainSite
	) :void {
		$this->isMainNetwork = $isMainNetwork;
		$this->isMainSite = $isMainSite;
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'targeted/plugin.php' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
				],
				'theme'  => [],
				'core'   => [],
			],
			'build_missing_snapshots' => true,
			'wpv' => [ 'attempts' => 0, 'due_at' => 1700000000 ],
		];
		$coordinator = new AssetCoordinator();

		$this->assertFalse( $coordinator->discoverMissingSnapshots() );
		$coordinator->runDueWork();

		$this->assertTrue( $this->state()[ 'build_missing_snapshots' ] );
		$this->assertSame( [], $this->state()[ 'assets' ][ 'plugin' ] );
		$this->assertArrayNotHasKey( 'wpv', $this->state() );
		$this->assertSame( [ [ 'plugin', 'targeted/plugin.php' ] ], $this->scans->assets );
		$this->assertSame( 1, $this->scans->wpvCalls );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );
	}

	public function provideNonOwnerTopologies() :array {
		return [
			'main network, secondary site' => [ true, false ],
			'secondary network, main site' => [ false, true ],
			'secondary network and site'   => [ false, false ],
		];
	}

	public function test_hourly_discovery_respects_self_upgrade_and_plugin_deletion_guards() :void {
		ServicesState::mergeItems( [
			'service_wpplugins' => new SnapshotPlugins( [
				new SnapshotPluginVo( 'missing-snapshot/plugin.php', '1.0.0' ),
			] ),
		] );
		$coordinator = new AssetCoordinator();

		$this->controller->is_my_upgrade = true;
		$this->assertFalse( $coordinator->discoverMissingSnapshots() );
		$this->assertArrayNotHasKey( 'build_missing_snapshots', $this->state() );

		$this->controller->is_my_upgrade = false;
		$this->controller->plugin_deleting = true;
		$this->assertFalse( $coordinator->discoverMissingSnapshots() );
		$this->assertArrayNotHasKey( 'build_missing_snapshots', $this->state() );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );
	}

	public function test_hourly_discovery_completes_without_queueing_when_no_snapshot_is_missing() :void {
		$this->assertTrue( ( new AssetCoordinator() )->discoverMissingSnapshots() );

		$this->assertArrayNotHasKey( 'build_missing_snapshots', $this->state() );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );
	}

	public function test_wakeup_keeps_earlier_event_and_adds_earlier_than_later_event() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'akismet/akismet.php' => [ 'attempts' => 0, 'due_at' => 1700000050 ],
				],
				'theme' => [],
				'core' => [],
			],
		];
		$this->addCron( 1700000040, 'icwp-wpsf-asset_coordinator', [ 1700000040 ] );

		( new AssetCoordinator() )->reconcileWakeup();
		$this->assertCount( 1, $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );

		$this->scheduled = [];
		$this->addCron( 1700000200, 'icwp-wpsf-asset_coordinator', [ 1700000200 ] );
		( new AssetCoordinator() )->reconcileWakeup();

		$this->assertSame(
			[ 1700000050, 1700000200 ],
			\array_column( $this->cronEvents( 'icwp-wpsf-asset_coordinator' ), 'timestamp' )
		);
		$this->assertSame( [], $this->unscheduled );
	}

	public function test_stale_later_wakeup_does_no_work_and_reconciles_future_state() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'first/plugin.php' => [ 'attempts' => 0, 'due_at' => 1700000050 ],
				],
				'theme' => [],
				'core' => [],
			],
		];
		$this->addCron( 1700000200, 'icwp-wpsf-asset_coordinator', [ 1700000200 ] );
		$coordinator = new AssetCoordinator();

		$coordinator->reconcileWakeup();
		$this->assertSame(
			[ 1700000050, 1700000200 ],
			\array_column( $this->cronEvents( 'icwp-wpsf-asset_coordinator' ), 'timestamp' )
		);

		unset( $this->scheduled[ 1700000050 ]['icwp-wpsf-asset_coordinator'] );
		$this->request->timestamp = 1700000050;
		$coordinator->runDueWork( 1700000050 );
		$this->assertSame( [ [ 'plugin', 'first/plugin.php' ] ], $this->scans->assets );
		$this->assertSame( [], $this->state()[ 'assets' ][ 'plugin' ] );

		$this->assertTrue( $coordinator->enqueueAsset( 'theme', 'future-theme', 310 ) );
		$this->assertSame(
			[ 1700000200 ],
			\array_column( $this->cronEvents( 'icwp-wpsf-asset_coordinator' ), 'timestamp' )
		);

		unset( $this->scheduled[ 1700000200 ]['icwp-wpsf-asset_coordinator'] );
		$this->request->timestamp = 1700000200;
		$coordinator->runDueWork( 1700000200 );

		$this->assertSame( [ [ 'plugin', 'first/plugin.php' ] ], $this->scans->assets );
		$this->assertSame(
			[ 'attempts' => 0, 'due_at' => 1700000360 ],
			$this->state()[ 'assets' ][ 'theme' ]['future-theme']
		);
		$this->assertSame(
			[ 1700000360 ],
			\array_column( $this->cronEvents( 'icwp-wpsf-asset_coordinator' ), 'timestamp' )
		);
	}

	public function test_empty_state_never_removes_stale_wakeup() :void {
		$this->addCron( 1700000200, 'icwp-wpsf-asset_coordinator', [ 1700000200 ] );

		( new AssetCoordinator() )->reconcileWakeup();

		$this->assertCount( 1, $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );
		$this->assertSame( [], $this->unscheduled );
	}

	public function test_legacy_import_merges_exact_events_before_unscheduling() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'akismet/akismet.php' => [ 'attempts' => 3, 'due_at' => 0 ],
				],
				'theme' => [],
				'core' => [],
			],
			'wpv' => [ 'attempts' => 3, 'due_at' => 0 ],
		];
		$this->addCron( 1700000030, 'icwp-wpsf-afs_asset_change_cleanup', [ 'plugin', 'akismet/akismet.php', 1 ] );
		$this->addCron( 1700000020, 'icwp-wpsf-afs_asset_change_cleanup', [ 'plugin', 'akismet/akismet.php', 0 ] );
		$this->addCron( 1700000040, 'icwp-wpsf-afs_asset_change_cleanup', [ 'theme', 'twentytwentyfive', 0 ] );
		$this->addCron( 1700000050, 'icwp-wpsf-ptg_build_snapshots', [] );
		$this->addCron( 1700000015, 'icwp-wpsf-ondemand_scan_wpv', [] );
		$this->addCron( 1700000025, 'icwp-wpsf-ondemand_scan_wpv', [ 'ignored' ] );

		( new AssetCoordinator() )->execute();

		$state = $this->state();
		$this->assertSame(
			[ 'attempts' => 0, 'due_at' => 1700000020 ],
			$state[ 'assets' ][ 'plugin' ]['akismet/akismet.php' ]
		);
		$this->assertSame(
			[ 'attempts' => 0, 'due_at' => 1700000040 ],
			$state[ 'assets' ][ 'theme' ]['twentytwentyfive' ]
		);
		$this->assertTrue( $state[ 'build_missing_snapshots' ] );
		$this->assertSame( [ 'attempts' => 0, 'due_at' => 1700000015 ], $state[ 'wpv' ] );
		$this->assertCount( 6, $this->unscheduled );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-afs_asset_change_cleanup' ) );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-ptg_build_snapshots' ) );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-ondemand_scan_wpv' ) );
	}

	public function test_failed_legacy_persistence_leaves_every_event_scheduled() :void {
		$this->addCron( 1700000020, 'icwp-wpsf-afs_asset_change_cleanup', [ 'plugin', 'akismet/akismet.php', 0 ] );
		$this->addCron( 1700000030, 'icwp-wpsf-ptg_build_snapshots', [] );
		$this->addCron( 1700000040, 'icwp-wpsf-ondemand_scan_wpv', [] );
		$this->persistWrites = false;
		$this->updateResult = false;

		( new AssetCoordinator() )->execute();

		$this->assertSame( [], $this->unscheduled );
		$this->assertCount( 3, $this->legacyCronEvents() );
		$this->assertContains(
			'Shield asset coordinator state write failed.',
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages
		);
	}

	/**
	 * @dataProvider provideNonOwnerTopologies
	 */
	public function test_non_owner_leaves_legacy_snapshot_build_for_the_owner(
		bool $isMainNetwork,
		bool $isMainSite
	) :void {
		$this->isMainNetwork = $isMainNetwork;
		$this->isMainSite = $isMainSite;
		$this->addCron( 1700000030, 'icwp-wpsf-ptg_build_snapshots', [] );

		( new AssetCoordinator() )->execute();

		$this->assertArrayNotHasKey( 'build_missing_snapshots', $this->state() );
		$this->assertCount( 1, $this->cronEvents( 'icwp-wpsf-ptg_build_snapshots' ) );
		$this->assertSame( [], $this->cronEvents( 'icwp-wpsf-asset_coordinator' ) );
	}

	public function test_unchanged_update_result_is_accepted_only_for_exact_stored_state() :void {
		$this->updateResult = false;
		$coordinator = new AssetCoordinator();

		$this->persistWrites = true;
		$this->assertTrue( $coordinator->enqueueAsset( 'plugin', 'akismet/akismet.php', 60 ) );

		$this->persistWrites = false;
		\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages = [];
		$this->assertFalse( $coordinator->enqueueAsset( 'theme', 'twentytwentyfive', 60 ) );
		$this->assertArrayNotHasKey( 'twentytwentyfive', $this->state()[ 'assets' ][ 'theme' ] );
		$this->assertCount(
			1,
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinatorTestLog::$messages
		);
	}

	public function test_malformed_state_normalizes_without_warnings_on_next_merge() :void {
		$this->options[ $this->optionKey() ] = [
			'assets' => [
				'plugin' => [
					'' => [ 'attempts' => 0, 'due_at' => 1 ],
					'bad-one/plugin.php' => 'not-a-record',
					'bad-two/plugin.php' => [ 'attempts' => -1, 'due_at' => 1 ],
					'bad-three/plugin.php' => [ 'attempts' => 0, 'due_at' => '1' ],
					'terminal/plugin.php' => [ 'attempts' => 99, 'due_at' => 123 ],
					'valid-conditioned/plugin.php' => [
						'attempts'                   => 1,
						'due_at'                     => 50,
						'required_published_version' => '1.2.3',
					],
					'blank-conditioned/plugin.php' => [
						'attempts'                   => 0,
						'due_at'                     => 50,
						'required_published_version' => ' ',
					],
					'null-conditioned/plugin.php' => [
						'attempts'                   => 0,
						'due_at'                     => 50,
						'required_published_version' => null,
					],
				],
				'core' => [
					'core' => [
						'attempts'                   => 0,
						'due_at'                     => 50,
						'required_published_version' => '1.0.0',
					],
				],
				'unknown' => [
					'ignored' => [ 'attempts' => 0, 'due_at' => 1 ],
				],
			],
			'build_missing_snapshots' => 'yes',
			'wpv' => [ 'attempts' => -1, 'due_at' => 1 ],
		];

		$this->assertTrue( ( new AssetCoordinator() )->enqueueAsset( 'theme', ' valid-theme ', 5 ) );

		$this->assertSame( [
			'assets' => [
				'plugin' => [
					'terminal/plugin.php' => [ 'attempts' => 3, 'due_at' => 0 ],
					'valid-conditioned/plugin.php' => [
						'attempts'                   => 1,
						'due_at'                     => 50,
						'required_published_version' => '1.2.3',
					],
				],
				'theme' => [
					'valid-theme' => [ 'attempts' => 0, 'due_at' => 1700000005 ],
				],
				'core' => [],
			],
		], $this->state() );
	}

	public function test_non_array_state_normalizes_before_merge() :void {
		$this->options[ $this->optionKey() ] = 'not-state';

		$this->assertTrue( ( new AssetCoordinator() )->enqueueAsset( 'theme', ' valid-theme ', 5 ) );

		$this->assertSame( [
			'assets' => [
				'plugin' => [],
				'theme' => [
					'valid-theme' => [ 'attempts' => 0, 'due_at' => 1700000005 ],
				],
				'core' => [],
			],
		], $this->state() );
	}

	private function registerWordPressFunctions() :void {
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_main_network' )->alias( fn() :bool => $this->isMainNetwork );
		Functions\when( 'is_main_site' )->alias( fn() :bool => $this->isMainSite );
		Functions\when( 'untrailingslashit' )->alias( static fn( string $path ) :string => \rtrim( $path, '/\\' ) );
		Functions\when( 'path_join' )->alias(
			static fn( string $a, string $b ) :string => \str_replace(
				'\\',
				'/',
				\rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' )
			)
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => (string)\json_encode( $data ) );
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		Functions\when( 'add_action' )->alias( function (
			string $hook,
			$callback,
			int $priority = 10,
			int $acceptedArgs = 1
		) :bool {
			$this->actions[] = \compact( 'hook', 'callback', 'priority', 'acceptedArgs' );
			return true;
		} );
		Functions\when( 'add_filter' )->alias( function (
			string $hook,
			$callback,
			int $priority = 10,
			int $acceptedArgs = 1
		) :bool {
			$this->filters[] = \compact( 'hook', 'callback', 'priority', 'acceptedArgs' );
			return true;
		} );
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			return \array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
		} );
		Functions\when( 'update_option' )->alias( function (
			string $key,
			$value,
			$autoload = null
		) :bool {
			$this->autoloadValues[] = $autoload;
			if ( $this->persistWrites ) {
				$this->options[ $key ] = $value;
			}
			return $this->updateResult;
		} );
		Functions\when( 'delete_option' )->alias( function ( string $key ) :bool {
			$exists = \array_key_exists( $key, $this->options );
			unset( $this->options[ $key ] );
			return $exists;
		} );
		Functions\when( 'wp_schedule_single_event' )->alias( function (
			int $timestamp,
			string $hook,
			array $args = []
		) :bool {
			$this->addCron( $timestamp, $hook, $args );
			return true;
		} );
		Functions\when( 'wp_unschedule_event' )->alias( function (
			int $timestamp,
			string $hook,
			array $args = []
		) :bool {
			$this->unscheduled[] = \compact( 'timestamp', 'hook', 'args' );
			foreach ( $this->scheduled[ $timestamp ][ $hook ] ?? [] as $key => $instance ) {
				if ( ( $instance[ 'args' ] ?? [] ) === $args ) {
					unset( $this->scheduled[ $timestamp ][ $hook ][ $key ] );
				}
			}
			return true;
		} );
	}

	private function installController() :void {
		$this->controller = UnitTestControllerFactory::install( null, null, (object)[
			'cfg' => new class {
				public array $properties = [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				];
			},
			'is_my_upgrade' => false,
			'cache_dir_handler' => new class {
				public function locateExistingDir() :string {
					return '';
				}
			},
			'comps' => (object)[
				'scans' => $this->scans,
			],
		] );
	}

	private function installPublishedSnapshot( SnapshotPluginVo $asset ) :void {
		$this->resetHashesStorageDir();
		$root = \str_replace( '\\', '/', $this->createTrackedTempDir( 'shield-coordinator-snapshot-' ) );
		$this->controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );
		ServicesState::mergeItems( [
			'service_wpfs'     => new CacheStoreTestFs(),
			'service_wpplugins' => new SnapshotPlugins( [ $asset ] ),
		] );
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [ 'plugin.php' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ] )
			->setSnapMeta( [
				'version'     => $asset->Version,
				'unique_id'   => $asset->file,
				'live_hashes' => true,
			] )
			->save();
	}

	private function resetHashesStorageDir() :void {
		$reflection = new \ReflectionClass( HashesStorageDir::class );
		foreach ( [ 'dir', 'rootDir' ] as $propertyName ) {
			if ( $reflection->hasProperty( $propertyName ) ) {
				$property = $reflection->getProperty( $propertyName );
				$property->setAccessible( true );
				$property->setValue( null, null );
			}
		}
	}

	private function state() :array {
		return $this->options[ $this->optionKey() ] ?? [
			'assets' => [
				'plugin' => [],
				'theme' => [],
				'core' => [],
			],
		];
	}

	private function assetRecord() :array {
		return $this->state()[ 'assets' ][ 'plugin' ]['deleted/deleted.php' ];
	}

	private function optionKey() :string {
		return 'icwp-wpsf-asset_coordinator_state';
	}

	private function assertHook( array $hooks, string $name, int $priority, int $acceptedArgs ) :void {
		$matching = \array_values( \array_filter(
			$hooks,
			static fn( array $hook ) :bool => $hook[ 'hook' ] === $name
		) );
		$this->assertCount( 1, $matching );
		$this->assertSame( $priority, $matching[ 0 ][ 'priority' ] );
		$this->assertSame( $acceptedArgs, $matching[ 0 ][ 'acceptedArgs' ] );
	}

	private function assertNotHooked( array $hooks, string $name ) :void {
		$this->assertSame( [], \array_values( \array_filter(
			$hooks,
			static fn( array $hook ) :bool => $hook[ 'hook' ] === $name
		) ) );
	}

	private function addCron( int $timestamp, string $hook, array $args ) :void {
		$this->scheduled[ $timestamp ][ $hook ][ \md5( \serialize( $args ) ) ] = [
			'schedule' => false,
			'args'     => $args,
		];
		\ksort( $this->scheduled, \SORT_NUMERIC );
	}

	private function cronEvents( string $hook ) :array {
		$events = [];
		foreach ( $this->scheduled as $timestamp => $hooks ) {
			foreach ( $hooks[ $hook ] ?? [] as $instance ) {
				$events[] = [
					'timestamp' => (int)$timestamp,
					'args'      => $instance[ 'args' ] ?? [],
				];
			}
		}
		return $events;
	}

	private function legacyCronEvents() :array {
		return \array_merge(
			$this->cronEvents( 'icwp-wpsf-afs_asset_change_cleanup' ),
			$this->cronEvents( 'icwp-wpsf-ptg_build_snapshots' ),
			$this->cronEvents( 'icwp-wpsf-ondemand_scan_wpv' )
		);
	}
}

class AssetCoordinatorTestCron extends Cron {

	private array $crons;

	public function __construct( array &$crons ) {
		$this->crons = &$crons;
	}

	public function getCrons( bool $onlyReadyToRunNow = false ) :array {
		unset( $onlyReadyToRunNow );
		return $this->crons;
	}
}

class AssetCoordinatorTestRequest extends Request {

	public int $timestamp;

	public function __construct( int $timestamp ) {
		$this->timestamp = $timestamp;
	}

	public function ts( bool $update = true ) :int {
		unset( $update );
		return $this->timestamp;
	}
}

class AssetCoordinatorTestScans {

	public array $assets = [];
	public bool $assetResult = true;
	public int $wpvCalls = 0;
	public ?StartScansResult $wpvResult = null;
	public $beforeStart = null;

	public function startAfsAssetScan( string $assetType, string $assetKey, bool $resetIgnored = false ) :bool {
		unset( $resetIgnored );
		if ( \is_callable( $this->beforeStart ) ) {
			( $this->beforeStart )( $assetType, $assetKey );
		}
		$this->assets[] = [ $assetType, $assetKey ];
		return $this->assetResult;
	}

	public function startNewScans( array $scans ) :StartScansResult {
		$this->wpvCalls++;
		return $this->wpvResult ?? StartScansResult::fromRequested( $scans )
			->addStarted( 'wpv', 1 );
	}
}

class AssetCoordinatorTestPlugins extends Plugins {

	public function getPluginsAsVo() :array {
		return [];
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $file, $reload );
		return null;
	}
}

class AssetCoordinatorTestThemes extends Themes {

	public function getThemesAsVo() :array {
		return [];
	}

	public function getCurrent() {
		return new class {
			public function get_stylesheet() :string {
				return 'missing-current-theme';
			}
		};
	}

	public function isActiveThemeAChild() :bool {
		return false;
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $stylesheet, $reload );
		return null;
	}
}

class AssetCoordinatorTestCoreHashes extends CoreFileHashes {

	public function isReady() :bool {
		return true;
	}
}

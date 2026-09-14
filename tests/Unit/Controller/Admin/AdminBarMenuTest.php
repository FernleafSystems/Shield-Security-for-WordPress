<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( 'WP_Admin_Bar' ) ) {
		class WP_Admin_Bar {
			public array $nodes = [];

			public function add_node( array $node ) :void {
				$this->nodes[] = $node;
			}
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Admin {

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin\AdminBarMenu;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\AdminBarScanSummaryCache;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	Users
};

class AdminBarMenuTest extends BaseUnitTest {

	private array $actions = [];
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'add_action' )->alias( function ( string $hook, callable $callback ) :bool {
			$this->actions[ $hook ][] = $callback;
			return true;
		} );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_admin_bar_menu_filter_defaults_to_enabled() :void {
		$cache = new AdminBarSummaryCacheSpy( null );
		$counts = new AdminBarCountsSpy( $this->emptySummary() );
		$this->installGateEnvironment( true, $counts, $cache );

		( new AdminBarMenu() )->execute();

		$this->assertCount( 1, $this->actions[ 'admin_bar_menu' ] ?? [] );
	}

	public function test_admin_bar_menu_filter_can_disable_registration_without_loading_scan_status() :void {
		$cache = new AdminBarSummaryCacheSpy( null );
		$counts = new AdminBarCountsSpy( $this->emptySummary() );
		$this->installGateEnvironment( false, $counts, $cache );

		( new AdminBarMenu() )->execute();

		$this->assertSame( [], $this->actions[ 'admin_bar_menu' ] ?? [] );
		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 0, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
	}

	public function test_security_admin_uses_cached_scan_summary_on_non_plugin_pages() :void {
		$cache = new AdminBarSummaryCacheSpy( $this->exactSummary() );
		$counts = new AdminBarCountsSpy( [
			'counts'    => [],
			'total'     => 99,
			'is_capped' => true,
		] );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 0, $counts->loadCalls );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( 0, $cache->readBoundedCalls );
		$this->assertSame( 0, $cache->storeBoundedCalls );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '6', 'shield-counter--issue' );
		$this->assertCount( 1, $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
	}

	public function test_admin_bar_uses_same_cached_scan_summary_on_plugin_pages() :void {
		$cache = new AdminBarSummaryCacheSpy( $this->exactSummary() );
		$counts = new AdminBarCountsSpy( $this->emptySummary() );
		$this->installController( true, true, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '6', 'shield-counter--issue' );
		$this->assertCount( 1, $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
	}

	public function test_admin_bar_uses_bounded_scan_summary_when_cache_missing_on_non_plugin_pages() :void {
		$cache = new AdminBarSummaryCacheSpy( null );
		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [ false ], $counts->forceExactArgs );
		$this->assertSame( 1, $counts->loadCalls );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( 1, $cache->readBoundedCalls );
		$this->assertSame( 1, $cache->storeBoundedCalls );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '99+', 'shield-counter--issue' );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
	}

	public function test_admin_bar_refreshes_exact_scan_summary_when_cache_missing_on_plugin_pages() :void {
		$cache = new AdminBarSummaryCacheSpy( null, $this->exactSummary() );
		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( true, true, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $counts->loadCalls );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 1, $cache->refreshCalls );
		$this->assertSame( 0, $cache->readBoundedCalls );
		$this->assertSame( 0, $cache->storeBoundedCalls );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '6', 'shield-counter--issue' );
		$this->assertCount( 1, $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
	}

	public function test_admin_bar_shows_top_node_only_when_cached_summary_is_empty() :void {
		$cache = new AdminBarSummaryCacheSpy( $this->emptySummary() );
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$this->installController( true, true, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '0', 'shield-counter--ok' );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
	}

	public function test_non_security_admin_gets_top_scan_status_without_details() :void {
		$usersDb = new AdminBarUsersDbSpy( [
			[
				'user_id'       => 7,
				'last_login_at' => 1234,
				'ip'            => '203.0.113.7',
				'user_login'    => 'operator',
			],
		] );
		$cache = new AdminBarSummaryCacheSpy( $this->exactSummary() );
		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( false, true, $counts, $cache, $usersDb );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 0, $usersDb->selectCalls );
		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '6', 'shield-counter--issue' );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertNull( $this->maybeNodeById( $adminBar, 'shield-meta-7' ) );
	}

	public function test_non_security_admin_uses_bounded_status_without_refresh_when_cache_missing() :void {
		$usersDb = new AdminBarUsersDbSpy( [
			[
				'user_id'       => 7,
				'last_login_at' => 1234,
				'ip'            => '203.0.113.7',
				'user_login'    => 'operator',
			],
		] );
		$cache = new AdminBarSummaryCacheSpy( null, $this->exactSummary() );
		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( false, true, $counts, $cache, $usersDb );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 0, $usersDb->selectCalls );
		$this->assertSame( [ false ], $counts->forceExactArgs );
		$this->assertSame( 1, $counts->loadCalls );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( 1, $cache->readBoundedCalls );
		$this->assertSame( 1, $cache->storeBoundedCalls );
		$this->assertTopNode( $adminBar, '/admin/scans/overview?zone=scans', '99+', 'shield-counter--issue' );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertNull( $this->maybeNodeById( $adminBar, 'shield-meta-7' ) );
	}

	public function test_later_request_reuses_bounded_cache_without_loading_counts() :void {
		$firstCache = new AdminBarSummaryCacheSpy( null );
		$firstCounts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( true, false, $firstCounts, $firstCache );

		$this->buildAdminBar();

		$this->assertSame( 1, $firstCounts->loadCalls );
		$this->assertSame( [ false ], $firstCounts->forceExactArgs );
		$this->assertSame( 1, $firstCache->storeBoundedCalls );
		$this->assertSame( $this->boundedSummary(), $firstCache->storedBoundedSummary );

		$this->actions = [];
		$secondCache = new AdminBarSummaryCacheSpy( null, null, $firstCache->storedBoundedSummary );
		$secondCounts = new AdminBarCountsSpy( $this->emptySummary() );
		$this->installController( true, false, $secondCounts, $secondCache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 0, $secondCounts->loadCalls );
		$this->assertSame( [], $secondCounts->forceExactArgs );
		$this->assertSame( 1, $secondCache->readBoundedCalls );
		$this->assertSame( 0, $secondCache->storeBoundedCalls );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
	}

	public function test_uncapped_bounded_cache_never_enables_exact_details() :void {
		$bounded = [
			'counts'    => [],
			'total'     => 4,
			'is_capped' => false,
		];
		$cache = new AdminBarSummaryCacheSpy( null, null, $bounded );
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 0, $counts->loadCalls );
		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertTopCounterLabel( $adminBar, '4' );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
	}

	public function test_rejected_bounded_store_keeps_warm_computed_summary_non_exact() :void {
		$cache = new AdminBarSummaryCacheSpy( null );
		$cache->acceptBoundedStore = false;
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [ false ], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->storeBoundedCalls );
		$this->assertSame( $this->exactSummary(), $cache->storedBoundedSummary );
		$this->assertTopCounterLabel( $adminBar, '6' );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
	}

	/**
	 * @dataProvider transientFailureProvider
	 */
	public function test_transient_failures_do_not_break_admin_bar_rendering( string $failure ) :void {
		switch ( $failure ) {
			case 'get':
				Functions\when( 'get_transient' )->alias( static function () {
					throw new \RuntimeException( 'get failed' );
				} );
				Functions\when( 'set_transient' )->justReturn( true );
				break;

			case 'set':
				Functions\when( 'get_transient' )->justReturn( false );
				Functions\when( 'set_transient' )->alias( static function () {
					throw new \RuntimeException( 'set failed' );
				} );
				break;

			case 'delete':
				Functions\when( 'get_transient' )->justReturn( [
					'counts'    => [ 'invalid' => 1 ],
					'total'     => 1,
					'is_capped' => false,
				] );
				Functions\when( 'delete_transient' )->alias( static function () {
					throw new \RuntimeException( 'delete failed' );
				} );
				Functions\when( 'set_transient' )->justReturn( true );
				break;
		}

		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( true, false, $counts, new AdminBarScanSummaryCache() );

		$adminBar = $this->buildAdminBar();

		$this->assertCount( 1, $this->topLevelNodes( $adminBar ) );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
	}

	public static function transientFailureProvider() :array {
		return [
			'get'    => [ 'get' ],
			'set'    => [ 'set' ],
			'delete' => [ 'delete' ],
		];
	}

	public function test_admin_bar_renders_recent_users_on_security_admin_plugin_pages() :void {
		$usersDb = new AdminBarUsersDbSpy( [
			[
				'user_id'       => 7,
				'last_login_at' => 1234,
				'ip'            => '203.0.113.7',
				'user_login'    => 'operator',
			],
		] );
		$cache = new AdminBarSummaryCacheSpy( $this->emptySummary() );
		$counts = new AdminBarCountsSpy( $this->emptySummary() );
		$this->installController( true, true, $counts, $cache, $usersDb );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 1, $usersDb->selectCalls );
		$this->assertCount( 1, $this->topMenuChildGroups( $adminBar ) );
		$this->assertStringContainsString( '/wp-admin/user-edit.php?user_id=7', $this->nodeById( $adminBar, 'shield-meta-7' )[ 'title' ] );
		$this->assertNoIpNodes( $adminBar );
	}

	public function test_admin_bar_does_not_query_or_render_recent_users_on_non_plugin_pages() :void {
		$usersDb = new AdminBarUsersDbSpy( [
			[
				'user_id'       => 7,
				'last_login_at' => 1234,
				'ip'            => '203.0.113.7',
				'user_login'    => 'operator',
			],
		] );
		$cache = new AdminBarSummaryCacheSpy( null );
		$counts = new AdminBarCountsSpy( $this->boundedSummary() );
		$this->installController( true, false, $counts, $cache, $usersDb );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( 0, $usersDb->selectCalls );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
		$this->assertNull( $this->maybeNodeById( $adminBar, 'shield-meta-7' ) );
		$this->assertNoIpNodes( $adminBar );
	}

	private function buildAdminBar() :\WP_Admin_Bar {
		( new AdminBarMenuPublicPathTestSubject() )->execute();
		$this->assertCount( 1, $this->actions[ 'admin_bar_menu' ] ?? [] );

		$adminBar = new \WP_Admin_Bar();
		$this->actions[ 'admin_bar_menu' ][ 0 ]( $adminBar );

		return $adminBar;
	}

	private function assertTopNode(
		\WP_Admin_Bar $adminBar,
		string $expectedHref,
		string $expectedCounter,
		string $expectedCounterClass
	) :void {
		$topNodes = $this->topLevelNodes( $adminBar );
		$this->assertCount( 1, $topNodes );
		$this->assertSame( $expectedHref, $topNodes[ 0 ][ 'href' ] );
		$this->assertStringContainsString( $expectedCounter, $topNodes[ 0 ][ 'title' ] );
		$this->assertStringContainsString( $expectedCounterClass, $topNodes[ 0 ][ 'title' ] );
	}

	private function assertTopCounterLabel( \WP_Admin_Bar $adminBar, string $expectedCounter ) :void {
		$topNodes = $this->topLevelNodes( $adminBar );
		$this->assertCount( 1, $topNodes );
		$this->assertStringContainsString( $expectedCounter, $topNodes[ 0 ][ 'title' ] );
	}

	private function assertNoIpNodes( \WP_Admin_Bar $adminBar ) :void {
		$this->assertSame(
			[],
			\array_values( \array_filter(
				\array_column( $adminBar->nodes, 'id' ),
				static fn( string $id ) :bool => \strpos( $id, 'shield-ip-' ) === 0
			) )
		);
	}

	private function scanChildNodeIds( \WP_Admin_Bar $adminBar ) :array {
		return \array_values( \array_filter(
			\array_column( $adminBar->nodes, 'id' ),
			static fn( string $id ) :bool => \strpos( $id, 'shield-problems-scan-' ) === 0
		) );
	}

	private function topLevelNodes( \WP_Admin_Bar $adminBar ) :array {
		return \array_values( \array_filter(
			$adminBar->nodes,
			static fn( array $node ) :bool => !isset( $node[ 'parent' ] )
		) );
	}

	private function topMenuChildGroups( \WP_Admin_Bar $adminBar ) :array {
		$topNodes = $this->topLevelNodes( $adminBar );
		$this->assertCount( 1, $topNodes );
		$topNodeID = $topNodes[ 0 ][ 'id' ];

		return \array_values( \array_filter(
			$adminBar->nodes,
			static fn( array $node ) :bool => ( $node[ 'parent' ] ?? null ) === $topNodeID
		) );
	}

	private function maybeNodeById( \WP_Admin_Bar $adminBar, string $id ) :?array {
		foreach ( $adminBar->nodes as $node ) {
			if ( ( $node[ 'id' ] ?? null ) === $id ) {
				return $node;
			}
		}

		return null;
	}

	private function nodeById( \WP_Admin_Bar $adminBar, string $id ) :array {
		$node = $this->maybeNodeById( $adminBar, $id );
		$this->assertNotNull( $node, 'Admin bar node not found: '.$id );

		return $node;
	}

	private function installController(
		bool $isPluginAdmin,
		bool $isPluginAdminPageRequest,
		AdminBarCountsSpy $counts,
		?object $cache = null,
		?AdminBarUsersDbSpy $usersDb = null
	) :void {
		$usersDb = $usersDb ?? new AdminBarUsersDbSpy( [] );
		ServicesState::mergeItems( [
			'service_wpdb'    => $usersDb,
			'service_wpusers' => new AdminBarUsersServiceSpy(),
		] );

		$controller = new class( $isPluginAdmin, $isPluginAdminPageRequest, $counts, $cache ) extends Controller {
			public UnitTestPluginUrls $plugin_urls;
			public object $comps;
			public object $db_con;
			public object $labels;
			private bool $pluginAdmin;
			private bool $pluginAdminPageRequest;
			private AdminBarCountsSpy $counts;
			private object $cache;

			public function __construct(
				bool $pluginAdmin,
				bool $pluginAdminPageRequest,
				AdminBarCountsSpy $counts,
				?object $cache
			) {
				$this->pluginAdmin = $pluginAdmin;
				$this->pluginAdminPageRequest = $pluginAdminPageRequest;
				$this->counts = $counts;
				$this->cache = $cache ?? new AdminBarSummaryCacheSpy( null );
				$this->plugin_urls = new UnitTestPluginUrls();
				$this->labels = (object)[ 'Name' => 'Shield' ];
				$this->db_con = (object)[
					'user_meta' => new AdminBarTableSpy( 'shield_user_meta' ),
					'ips'       => new AdminBarTableSpy( 'shield_ips' ),
				];
				$this->comps = (object)[
					'scans' => new class( $this->counts, $this->cache ) {
						private AdminBarCountsSpy $counts;
						private object $cache;

						public function __construct( AdminBarCountsSpy $counts, object $cache ) {
							$this->counts = $counts;
							$this->cache = $cache;
						}

						public function getScanResultsCount() :AdminBarCountsSpy {
							$this->counts->loadCalls++;
							return $this->counts;
						}

						public function getAdminBarScanSummaryCache() :object {
							return $this->cache;
						}
					},
				];
			}

			public function isPluginAdmin() :bool {
				return $this->pluginAdmin;
			}

			public function isPluginAdminPageRequest() :bool {
				return $this->pluginAdminPageRequest;
			}

			public function prefix( string $suffix = '', string $glue = '-' ) :string {
				return 'shield'.( $suffix === '' ? '' : $glue.$suffix );
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installGateEnvironment(
		bool $showMenu,
		AdminBarCountsSpy $counts,
		AdminBarSummaryCacheSpy $cache
	) :void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'shield/show_admin_bar_menu', true )
			->andReturn( $showMenu );

		ServicesState::mergeItems( [
			'service_wpusers' => new AdminBarGateUsersSpy(),
		] );

		$controller = new class( $counts, $cache ) extends Controller {
			public object $comps;
			public object $this_req;

			public function __construct( AdminBarCountsSpy $counts, AdminBarSummaryCacheSpy $cache ) {
				$this->this_req = (object)[
					'is_force_off' => false,
					'wp_is_ajax'   => false,
				];
				$this->comps = (object)[
					'scans' => new class( $counts, $cache ) {
						private AdminBarCountsSpy $counts;
						private AdminBarSummaryCacheSpy $cache;

						public function __construct( AdminBarCountsSpy $counts, AdminBarSummaryCacheSpy $cache ) {
							$this->counts = $counts;
							$this->cache = $cache;
						}

						public function getScanResultsCount() :AdminBarCountsSpy {
							$this->counts->loadCalls++;
							return $this->counts;
						}

						public function getAdminBarScanSummaryCache() :AdminBarSummaryCacheSpy {
							return $this->cache;
						}
					},
				];
			}

			public function isValidAdminArea( bool $checkUserPerms = false ) :bool {
				return true;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function exactSummary() :array {
		return [
			'counts'    => [
				'malware'           => 2,
				'wp_files'          => 1,
				'plugin_files'      => 0,
				'theme_files'       => 0,
				'abandoned'         => 0,
				'vulnerable_assets' => 3,
			],
			'total'     => 6,
			'is_capped' => false,
		];
	}

	private function emptySummary() :array {
		return [
			'counts'    => [
				'malware'           => 0,
				'wp_files'          => 0,
				'plugin_files'      => 0,
				'theme_files'       => 0,
				'abandoned'         => 0,
				'vulnerable_assets' => 0,
			],
			'total'     => 0,
			'is_capped' => false,
		];
	}

	private function boundedSummary() :array {
		return [
			'counts'    => [],
			'total'     => 99,
			'is_capped' => true,
		];
	}
}

class AdminBarMenuPublicPathTestSubject extends AdminBarMenu {
	protected function canRun() :bool {
		return true;
	}
}

class AdminBarGateUsersSpy extends Users {

	public function isUserAdmin( $user = null ) {
		return true;
	}
}

class AdminBarCountsSpy {

	public int $loadCalls = 0;

	/**
	 * @var list<bool>
	 */
	public array $forceExactArgs = [];

	/**
	 * @param array{
	 *   counts:array<string,int>,
	 *   total:int,
	 *   is_capped:bool
	 * } $summary
	 */
	private array $summary;

	public function __construct( array $summary ) {
		$this->summary = $summary;
	}

	public function adminBarScanSummary( bool $forceExact = false ) :array {
		$this->forceExactArgs[] = $forceExact;
		return $this->summary;
	}
}

class AdminBarSummaryCacheSpy {

	public int $readCalls = 0;

	public int $refreshCalls = 0;

	public int $readBoundedCalls = 0;

	public int $storeBoundedCalls = 0;

	public bool $acceptBoundedStore = true;

	public ?array $storedBoundedSummary = null;

	private ?array $readSummary;

	private ?array $refreshSummary;

	private ?array $boundedReadSummary;

	/**
	 * @param array{
	 *   counts:array<string,int>,
	 *   total:int,
	 *   is_capped:bool
	 * }|null $readSummary
	 */
	public function __construct(
		?array $readSummary,
		?array $refreshSummary = null,
		?array $boundedReadSummary = null
	) {
		$this->readSummary = $readSummary;
		$this->refreshSummary = $refreshSummary;
		$this->boundedReadSummary = $boundedReadSummary;
	}

	public function read() :?array {
		$this->readCalls++;
		return $this->readSummary;
	}

	public function refresh( AdminBarCountsSpy $counts ) :?array {
		unset( $counts );
		$this->refreshCalls++;
		return $this->refreshSummary ?? $this->readSummary;
	}

	public function readBounded() :?array {
		$this->readBoundedCalls++;
		return $this->boundedReadSummary;
	}

	public function storeBounded( array $summary ) :?array {
		$this->storeBoundedCalls++;
		$this->storedBoundedSummary = $summary;
		return $this->acceptBoundedStore ? $summary : null;
	}
}

class AdminBarTableSpy {

	private string $table;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function getTable() :string {
		return $this->table;
	}
}

class AdminBarUsersDbSpy extends Db {

	public int $selectCalls = 0;

	private array $rows;

	public function __construct( array $rows ) {
		$this->rows = $rows;
	}

	public function getTable_Users() :string {
		return 'wp_users';
	}

	public function selectCustom( $query, $format = null ) {
		unset( $query, $format );
		$this->selectCalls++;
		return $this->rows;
	}
}

class AdminBarUsersServiceSpy extends Users {

	public function getAdminUrl_ProfileEdit( $user = null ) :string {
		return '/wp-admin/user-edit.php?user_id='.(int)$user;
	}
}

}

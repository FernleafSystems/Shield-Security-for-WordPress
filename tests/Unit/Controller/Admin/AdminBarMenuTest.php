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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestPluginUrls
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

	public function test_admin_bar_uses_cached_scan_summary_on_non_plugin_pages() :void {
		$cache = new AdminBarSummaryCacheSpy( $this->exactSummary() );
		$counts = new AdminBarCountsSpy( [
			'counts'    => [],
			'total'     => 99,
			'is_capped' => true,
		] );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertCount( 1, $this->topLevelNodes( $adminBar ) );
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
		$this->assertCount( 1, $this->topLevelNodes( $adminBar ) );
		$this->assertCount( 1, $this->topMenuChildGroups( $adminBar ) );
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
	}

	public function test_admin_bar_shows_top_node_only_when_cache_missing() :void {
		$cache = new AdminBarSummaryCacheSpy( null );
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertCount( 1, $this->topLevelNodes( $adminBar ) );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
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
		$this->assertCount( 1, $this->topLevelNodes( $adminBar ) );
		$this->assertSame( [], $this->topMenuChildGroups( $adminBar ) );
	}

	public function test_admin_bar_returns_before_cache_or_count_work_for_unauthorised_users() :void {
		$cache = new AdminBarSummaryCacheSpy( $this->exactSummary() );
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$this->installController( false, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [], $adminBar->nodes );
		$this->assertSame( [], $counts->forceExactArgs );
		$this->assertSame( 0, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
	}

	private function buildAdminBar() :\WP_Admin_Bar {
		( new AdminBarMenuPublicPathTestSubject() )->execute();
		$this->assertCount( 1, $this->actions[ 'admin_bar_menu' ] ?? [] );

		$adminBar = new \WP_Admin_Bar();
		$this->actions[ 'admin_bar_menu' ][ 0 ]( $adminBar );

		return $adminBar;
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

	private function installController(
		bool $isPluginAdmin,
		bool $isPluginAdminPageRequest,
		AdminBarCountsSpy $counts,
		?AdminBarSummaryCacheSpy $cache = null
	) :void {
		$controller = new class( $isPluginAdmin, $isPluginAdminPageRequest, $counts, $cache ) extends Controller {
			public UnitTestPluginUrls $plugin_urls;
			public object $comps;
			public object $labels;
			private bool $pluginAdmin;
			private bool $pluginAdminPageRequest;
			private AdminBarCountsSpy $counts;
			private AdminBarSummaryCacheSpy $cache;

			public function __construct(
				bool $pluginAdmin,
				bool $pluginAdminPageRequest,
				AdminBarCountsSpy $counts,
				?AdminBarSummaryCacheSpy $cache
			) {
				$this->pluginAdmin = $pluginAdmin;
				$this->pluginAdminPageRequest = $pluginAdminPageRequest;
				$this->counts = $counts;
				$this->cache = $cache ?? new AdminBarSummaryCacheSpy( null );
				$this->plugin_urls = new UnitTestPluginUrls();
				$this->labels = (object)[ 'Name' => 'Shield' ];
				$this->comps = (object)[
					'scans' => new class( $this->counts, $this->cache ) {
						private AdminBarCountsSpy $counts;
						private AdminBarSummaryCacheSpy $cache;

						public function __construct( AdminBarCountsSpy $counts, AdminBarSummaryCacheSpy $cache ) {
							$this->counts = $counts;
							$this->cache = $cache;
						}

						public function getScanResultsCount() :AdminBarCountsSpy {
							return $this->counts;
						}

						public function getAdminBarScanSummaryCache() :AdminBarSummaryCacheSpy {
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
}

class AdminBarMenuPublicPathTestSubject extends AdminBarMenu {
	protected function canRun() :bool {
		return true;
	}
}

class AdminBarCountsSpy {

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

	private ?array $readSummary;

	/**
	 * @param array{
	 *   counts:array<string,int>,
	 *   total:int,
	 *   is_capped:bool
	 * }|null $readSummary
	 */
	public function __construct( ?array $readSummary ) {
		$this->readSummary = $readSummary;
	}

	public function read() :?array {
		$this->readCalls++;
		return $this->readSummary;
	}

	public function refresh( AdminBarCountsSpy $counts ) :?array {
		unset( $counts );
		$this->refreshCalls++;
		return $this->readSummary;
	}
}

}

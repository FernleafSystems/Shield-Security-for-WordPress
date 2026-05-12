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
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin\AdminBarMenu;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\Db;

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

	public function test_admin_bar_uses_cached_exact_counts_without_live_exact_counts_off_plugin_pages() :void {
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
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
	}

	public function test_admin_bar_uses_bounded_count_without_child_items_off_plugin_pages_when_cache_empty() :void {
		$counts = new AdminBarCountsSpy( [
			'counts'    => [],
			'total'     => 99,
			'is_capped' => true,
		] );
		$cache = new AdminBarSummaryCacheSpy( null );
		$this->installController( true, false, $counts, $cache );

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [ false ], $counts->forceExactArgs );
		$this->assertSame( 1, $cache->readCalls );
		$this->assertSame( 0, $cache->refreshCalls );
		$this->assertSame( [], $this->scanChildNodeIds( $adminBar ) );
		$this->assertCount( 2, $adminBar->nodes );
	}

	public function test_admin_bar_uses_exact_counts_and_builds_children_on_plugin_pages() :void {
		$counts = new AdminBarCountsSpy( $this->exactSummary() );
		$cache = new AdminBarSummaryCacheSpy( null, true );
		$this->installController( true, true, $counts, $cache );
		$this->installEmptyRecentDetailQueries();

		$adminBar = $this->buildAdminBar();

		$this->assertSame( [ true ], $counts->forceExactArgs );
		$this->assertSame( 0, $cache->readCalls );
		$this->assertSame( 1, $cache->refreshCalls );
		$this->assertSame(
			[ 'shield-problems-scan-malware', 'shield-problems-scan-wp', 'shield-problems-scan-wpv' ],
			$this->scanChildNodeIds( $adminBar )
		);
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
			static fn( string $id ) :bool => \str_starts_with( $id, 'shield-problems-scan-' )
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
			public object $db_con;
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
				$this->db_con = (object)[
					'ips'       => new AdminBarTableStub( 'ips', [
						'ip' => [ 'type' => 'varbinary' ],
					] ),
					'ip_rules'  => new AdminBarTableStub( 'ip_rules', [
						'ip_ref'    => [],
						'cidr'      => [],
						'is_range'  => [],
						'offenses'  => [],
						'type'      => [],
						'label'     => [],
						'can_export' => [],
						'last_access_at' => [],
						'blocked_at'     => [],
						'unblocked_at'   => [],
						'last_unblock_attempt_at' => [],
						'expires_at'     => [],
						'imported_at'    => [],
					] ),
					'user_meta' => new AdminBarTableStub( 'user_meta' ),
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

	private function installEmptyRecentDetailQueries() :void {
		ServicesState::installItems( [
			'service_wpdb' => new class extends Db {
				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					return [];
				}

				public function getPrefix( bool $siteBase = true ) :string {
					unset( $siteBase );
					return 'wp_';
				}

				public function getTable_Users() :string {
					return 'wp_users';
				}
			},
		] );
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
}

class AdminBarMenuPublicPathTestSubject extends AdminBarMenu {
	protected function canRun() :bool {
		return true;
	}
}

class AdminBarTableStub {

	private TableSchema $schema;

	public function __construct( string $slug, array $cols = [] ) {
		$this->schema = ( new TableSchema() )->applyFromArray( [
			'slug'        => $slug,
			'cols_custom' => $cols,
		] );
	}

	public function getTable() :string {
		return 'wp_'.$this->schema->slug;
	}

	public function getTableSchema() :TableSchema {
		return $this->schema;
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

	private bool $refreshThroughCounts;

	/**
	 * @param array{
	 *   counts:array<string,int>,
	 *   total:int,
	 *   is_capped:bool
	 * }|null $readSummary
	 */
	public function __construct( ?array $readSummary, bool $refreshThroughCounts = false ) {
		$this->readSummary = $readSummary;
		$this->refreshThroughCounts = $refreshThroughCounts;
	}

	public function read() :?array {
		$this->readCalls++;
		return $this->readSummary;
	}

	public function refresh( AdminBarCountsSpy $counts ) :?array {
		$this->refreshCalls++;
		return $this->refreshThroughCounts
			? $counts->adminBarScanSummary( true )
			: $this->readSummary;
	}
}
}

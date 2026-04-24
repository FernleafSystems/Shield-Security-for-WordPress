<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Admin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin\AdminBarMenu;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	UnitTestPluginUrls
};

class AdminBarMenuTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_hackguard_uses_bounded_count_without_child_items_off_plugin_pages() :void {
		$counts = new AdminBarCountsSpy( [
			'counts'    => [],
			'total'     => 99,
			'is_capped' => true,
		] );
		$this->installController( false, $counts );

		$group = $this->invokeNonPublicMethod( new AdminBarMenu(), 'hackGuard' );

		$this->assertSame( [ false ], $counts->forceExactArgs );
		$this->assertSame( [], $group[ 'items' ] );
		$this->assertSame( 99, $group[ 'warnings' ] );
		$this->assertTrue( $group[ 'warnings_capped' ] );
	}

	public function test_hackguard_uses_exact_counts_and_builds_children_on_plugin_pages() :void {
		$counts = new AdminBarCountsSpy( [
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
		] );
		$this->installController( true, $counts );

		$group = $this->invokeNonPublicMethod( new AdminBarMenu(), 'hackGuard' );

		$this->assertSame( [ true ], $counts->forceExactArgs );
		$this->assertSame( 6, $group[ 'warnings' ] );
		$this->assertFalse( $group[ 'warnings_capped' ] );
		$this->assertSame( [ 2, 1, 3 ], \array_column( $group[ 'items' ], 'warnings' ) );
		$this->assertCount( 3, \array_filter( \array_column( $group[ 'items' ], 'id' ), '\is_string' ) );
	}

	private function installController( bool $isPluginAdmin, AdminBarCountsSpy $counts ) :void {
		$controller = new class( $isPluginAdmin, $counts ) extends Controller {
			public UnitTestPluginUrls $plugin_urls;
			public object $comps;
			public object $labels;
			private bool $pluginAdmin;
			private AdminBarCountsSpy $counts;

			public function __construct( bool $pluginAdmin, AdminBarCountsSpy $counts ) {
				$this->pluginAdmin = $pluginAdmin;
				$this->counts = $counts;
				$this->plugin_urls = new UnitTestPluginUrls();
				$this->labels = (object)[ 'Name' => 'Shield' ];
				$this->comps = (object)[
					'scans' => new class( $this->counts ) {
						private AdminBarCountsSpy $counts;

						public function __construct( AdminBarCountsSpy $counts ) {
							$this->counts = $counts;
						}

						public function getScanResultsCount() :AdminBarCountsSpy {
							return $this->counts;
						}
					},
				];
			}

			public function isPluginAdmin() :bool {
				return $this->pluginAdmin;
			}

			public function prefix( string $suffix = '', string $glue = '-' ) :string {
				return 'shield'.( $suffix === '' ? '' : $glue.$suffix );
			}
		};

		PluginControllerInstaller::install( $controller );
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

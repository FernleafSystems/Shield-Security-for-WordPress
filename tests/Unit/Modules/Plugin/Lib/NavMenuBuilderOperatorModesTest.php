<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;

class NavMenuBuilderOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
	}

	protected function tearDown() :void {
		PluginStore::$plugin = null;
		parent::tearDown();
	}

	public function testConfigureModePrependsBackLinkAndSecurityGradesThenFilteredItems() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeNav( $this->baseMenuFixture(), PluginNavs::MODE_CONFIGURE );

		$this->assertSame(
			[
				'mode-selector-back',
				'mode-configure-grades',
				PluginNavs::NAV_ZONES,
				PluginNavs::NAV_RULES,
				PluginNavs::NAV_TOOLS,
			],
			\array_column( $menu, 'slug' )
		);

		$backLink = $menu[ 0 ];
		$this->assertSame( 'Back to Dashboard', $backLink[ 'title' ] );
		$this->assertSame( 'icon-speedometer', $backLink[ 'img' ] );
		$this->assertSame( 'backlink', $backLink[ 'group' ] );
		$this->assertSame( [ 'mode-back-link' ], $backLink[ 'classes' ] );

		$grades = $menu[ 1 ];
		$this->assertSame( 'Security Grades', $grades[ 'title' ] );
		$this->assertSame( '/admin/dashboard/grades', $grades[ 'href' ] );
		$this->assertFalse( $grades[ 'active' ] );
		$this->assertSame( [], $grades[ 'classes' ] );
		$this->assertSame( [], $grades[ 'sub_items' ] );
		$this->assertSame( 'icon-speedometer', $grades[ 'img' ] );
		$this->assertSame( 'Security At A Glance', $grades[ 'subtitle' ] );
	}

	public function testNonConfigureModeDoesNotAddSyntheticSecurityGradesItem() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeNav( $this->baseMenuFixture(), PluginNavs::MODE_ACTIONS );
		$slugs = \array_column( $menu, 'slug' );

		$this->assertSame(
			[
				'mode-selector-back',
				PluginNavs::NAV_SCANS,
			],
			$slugs
		);
		$this->assertNotContains( 'mode-configure-grades', $slugs );
		$this->assertSame( 'backlink', $menu[ 0 ][ 'group' ] );
		$this->assertSame( [ 'mode-back-link' ], $menu[ 0 ][ 'classes' ] );
		$this->assertSame( 'primary', $menu[ 1 ][ 'group' ] );
	}

	public function testConfigureModeGradesItemUsesEmptyMetadataWhenDashboardSourceMissing() :void {
		$this->installControllerStubs();

		$baseMenu = \array_values( \array_filter(
			$this->baseMenuFixture(),
			fn( array $item ) :bool => ( $item[ 'slug' ] ?? '' ) !== PluginNavs::NAV_DASHBOARD
		) );

		$menu = $this->invokeBuildModeNav( $baseMenu, PluginNavs::MODE_CONFIGURE );
		$grades = $menu[ 1 ];

		$this->assertSame( 'mode-configure-grades', $grades[ 'slug' ] );
		$this->assertSame( '', $grades[ 'img' ] );
		$this->assertSame( '', $grades[ 'subtitle' ] );
		$this->assertSame( 'primary', $grades[ 'group' ] );
	}

	public function testModeSelectorAssignsPrimaryAndMetaGroups() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeSelector( $this->baseMenuFixture() );

		$this->assertSame(
			[
				'mode-actions',
				'mode-investigate',
				'mode-configure',
				'mode-reports',
				PluginNavs::NAV_LICENSE,
			],
			\array_column( $menu, 'slug' )
		);

		$this->assertSame(
			[ 'primary', 'primary', 'primary', 'primary', 'meta' ],
			\array_column( $menu, 'group' )
		);
	}

	public function testNormalizeGroupNormalizesKnownValuesAndFallsBackToPrimary() :void {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'normalizeGroup' );
		$method->setAccessible( true );

		$this->assertSame( 'meta', $method->invoke( $builder, ' META ' ) );
		$this->assertSame( 'primary', $method->invoke( $builder, 'unexpected' ) );
	}

	public function testMarkGroupBoundaryAddsMarkerOnlyOnGroupTransition() :void {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'markGroupBoundary' );
		$method->setAccessible( true );

		$first = $method->invoke( $builder, [ 'group' => 'primary', 'classes' => [] ], '' );
		$this->assertNotContains( 'menu-group-break-before', $first[ 'classes' ] );

		$transition = $method->invoke( $builder, [ 'group' => 'meta', 'classes' => [] ], 'primary' );
		$this->assertContains( 'menu-group-break-before', $transition[ 'classes' ] );

		$sameGroup = $method->invoke( $builder, [ 'group' => 'meta', 'classes' => [] ], 'meta' );
		$this->assertNotContains( 'menu-group-break-before', $sameGroup[ 'classes' ] );
	}

	private function invokeBuildModeNav( array $baseMenu, string $mode ) :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'buildModeNav' );
		$method->setAccessible( true );
		return $method->invoke( $builder, $baseMenu, $mode );
	}

	private function invokeBuildModeSelector( array $baseMenu ) :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'buildModeSelector' );
		$method->setAccessible( true );
		return $method->invoke( $builder, $baseMenu );
	}

	private function newBuilderInstance() :NavMenuBuilder {
		/** @var NavMenuBuilder $builder */
		$builder = ( new \ReflectionClass( NavMenuBuilder::class ) )->newInstanceWithoutConstructor();
		return $builder;
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminHome() :string {
				return '/admin/home';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};

		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};
	}

	private function baseMenuFixture() :array {
		return [
			[
				'slug'     => PluginNavs::NAV_DASHBOARD,
				'title'    => 'Dashboard',
				'subtitle' => 'Security At A Glance',
				'img'      => 'icon-speedometer',
			],
			[
				'slug'  => PluginNavs::NAV_ZONES,
				'title' => 'Security Zones',
			],
			[
				'slug'  => PluginNavs::NAV_RULES,
				'title' => 'Rules',
			],
			[
				'slug'  => PluginNavs::NAV_TOOLS,
				'title' => 'Tools',
			],
			[
				'slug'  => PluginNavs::NAV_SCANS,
				'title' => 'Scans',
			],
			[
				'slug' => PluginNavs::NAV_LICENSE,
				'title' => 'Go PRO!',
			],
		];
	}
}

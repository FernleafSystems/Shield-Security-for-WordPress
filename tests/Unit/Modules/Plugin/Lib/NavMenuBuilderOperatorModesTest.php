<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestLicenseComponent,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestWhitelabelComponent,
	UnitTestZonesComponent
};
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class NavMenuBuilderOperatorModesTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_home_route_shows_mode_selector_and_home_only_sections() :void {
		$this->installController();
		$this->installRequest();

		$sidebar = $this->createBuilder()->build();

		$this->assertNull( $sidebar[ 'back_item' ] );
		$this->assertSame( PluginNavs::allOperatorModes(), \array_column( $sidebar[ 'mode_items' ], 'mode' ) );
		$this->assertSame( [], $sidebar[ 'tool_items' ] );
		$this->assertSame( PluginNavs::NAV_LICENSE, $sidebar[ 'home_license_item' ][ 'slug' ] ?? '' );
		$this->assertSame( 'warning', $sidebar[ 'home_license_item' ][ 'badge' ][ 'status' ] ?? '' );
		$this->assertNotSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertCount( 4, $sidebar[ 'home_connect_items' ] );
		$this->assertNotNull( $this->findItemBySlug( $sidebar[ 'home_connect_items' ], 'connect-helpdesk' ) );
	}

	public function test_actions_route_marks_current_mode_and_tool_and_shows_summary_badge() :void {
		$this->installController();
		$this->installRequest( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_SCANS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_SCANS_RUN,
		] );

		$sidebar = $this->createBuilder( [
			'has_items'   => true,
			'total_items' => 7,
			'severity'    => 'critical',
		] )->build();

		$this->assertSame( 'mode-selector-back', $sidebar[ 'back_item' ][ 'slug' ] ?? '' );
		$this->assertSame( '/admin/home', $sidebar[ 'back_item' ][ 'href' ] ?? '' );
		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertNull( $sidebar[ 'home_license_item' ] );

		$actionsMode = $this->findItemBySlug( $sidebar[ 'mode_items' ], 'mode-actions' );
		$this->assertTrue( (bool)( $actionsMode[ 'active' ] ?? false ) );
		$this->assertSame( '7', $actionsMode[ 'badge' ][ 'text' ] ?? '' );
		$this->assertSame( 'critical', $actionsMode[ 'badge' ][ 'status' ] ?? '' );

		$scanTool = $this->findItemBySlug( $sidebar[ 'tool_items' ], PluginNavs::NAV_SCANS.'-'.PluginNavs::SUBNAV_SCANS_RUN );
		$this->assertTrue( (bool)( $scanTool[ 'active' ] ?? false ) );
	}

	public function test_investigate_mode_shows_peer_tools_without_reintroducing_parent_activity_item() :void {
		$this->installController();
		$this->installRequest( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_ACTIVITY,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_LOGS,
		] );

		$toolItems = $this->createBuilder()->build()[ 'tool_items' ];

		$this->assertSame(
			[
				PluginNavs::NAV_IPS.'-'.PluginNavs::SUBNAV_IPS_RULES,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_TRAFFIC.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_ACTIVITY_SESSIONS,
			],
			\array_column( $toolItems, 'slug' )
		);
		$this->assertTrue( (bool)( $this->findItemBySlug( $toolItems, PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_LOGS )[ 'active' ] ?? false ) );
	}

	public function test_configure_mode_keeps_static_tools_and_configure_only_zone_component_entries() :void {
		$this->installController();
		$this->installRequest( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_TOOLS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_TOOLS_DEBUG,
		] );

		$toolItems = $this->createBuilder()->build()[ 'tool_items' ];

		$this->assertNotNull( $this->findItemBySlug( $toolItems, PluginNavs::NAV_WIZARD.'-'.PluginNavs::SUBNAV_WIZARD_WELCOME ) );
		$this->assertNotNull( $this->findItemBySlug( $toolItems, PluginNavs::NAV_TOOLS.'-whitelabel' ) );
		$this->assertNotNull( $this->findItemBySlug( $toolItems, PluginNavs::NAV_TOOLS.'-loginhide' ) );
		$this->assertNotNull( $this->findItemBySlug( $toolItems, PluginNavs::NAV_TOOLS.'-integrations' ) );
		$this->assertTrue( (bool)( $this->findItemBySlug( $toolItems, PluginNavs::NAV_TOOLS.'-'.PluginNavs::SUBNAV_TOOLS_DEBUG )[ 'active' ] ?? false ) );
		$this->assertFalse( (bool)( $this->findItemBySlug( $toolItems, PluginNavs::NAV_WIZARD.'-'.PluginNavs::SUBNAV_WIZARD_WELCOME )[ 'active' ] ?? true ) );
	}

	public function test_whitelabel_hides_home_connect_items() :void {
		$this->installController( isWhitelabelled: true );
		$this->installRequest();

		$sidebar = $this->createBuilder()->build();

		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertSame( [], $sidebar[ 'home_connect_items' ] );
	}

	private function installController( bool $isWhitelabelled = false, bool $isPremium = false ) :void {
		UnitTestControllerFactory::install(
			pluginUrls: new UnitTestPluginUrls(),
			extras: (object)[
				'cfg' => (object)[
					'properties' => [
						'slug_parent'      => 'icwp',
						'slug_plugin'      => 'wpsf',
						'base_permissions' => 'manage_options',
					],
				],
				'user_can_base_permissions' => true,
				'labels' => (object)[
					'url_helpdesk' => 'https://help.example.com',
					'Name'         => 'Shield',
				],
				'comps' => (object)[
					'license'    => new UnitTestLicenseComponent( $isPremium ),
					'whitelabel' => new UnitTestWhitelabelComponent( $isWhitelabelled ),
					'zones'      => new UnitTestZonesComponent(),
				],
			]
		);
	}

	private function installRequest( array $query = [] ) :void {
		$_GET = $query;
		ServicesState::installItems( [
			'service_request'          => new UnitTestRequest( $query ),
			'service_datamanipulation' => new DataManipulation(),
		] );
	}

	private function createBuilder( array $summary = [] ) :NavMenuBuilder {
		return new NavMenuBuilderTestDouble(
			\array_merge( [
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
			], $summary )
		);
	}

	private function findItemBySlug( array $items, string $slug ) :?array {
		foreach ( $items as $item ) {
			if ( ( $item[ 'slug' ] ?? '' ) === $slug ) {
				return $item;
			}
		}
		return null;
	}
}

class NavMenuBuilderTestDouble extends NavMenuBuilder {

	public function __construct( private array $summary ) {
	}

	protected function buildActionsQueueSummaryContract() :array {
		return $this->summary;
	}
}

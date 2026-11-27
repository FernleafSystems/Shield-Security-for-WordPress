<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Actions\PluginAdmin {

use Brain\Monkey\{
	Actions,
	Filters,
	Functions
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin\PluginAdminPageHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PageAdminPlugin;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumDashboardDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	RenderCapture,
	ServicesState,
	UnitTestActionRouter,
	UnitTestControllerFactory,
	UnitTestLicenseComponent,
	UnitTestPluginUrls,
	UnitTestZonesComponent
};

class PluginAdminPageHandlerTest extends BaseUnitTest {

	private array $subMenuCalls = [];

	private ?array $servicesSnapshot = null;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ) :string => \strtolower( \trim( $key ) ) );
		Functions\when( 'add_submenu_page' )->alias( function (
			string $parentSlug,
			string $pageTitle,
			string $menuTitle,
			string $capability,
			string $menuSlug,
			callable $callback
		) :string {
			$this->subMenuCalls[] = [
				'parent_slug' => $parentSlug,
				'page_title'  => $pageTitle,
				'menu_title'  => $menuTitle,
				'capability'  => $capability,
				'menu_slug'   => $menuSlug,
				'callback'    => $callback,
			];
			return 'hook-'.$menuSlug;
		} );
	}

	protected function tearDown() :void {
		if ( $this->servicesSnapshot !== null ) {
			ServicesState::restore( $this->servicesSnapshot );
		}
		unset( $GLOBALS[ 'title' ] );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_free_admin_submenu_uses_get_pro_security_highlighted_license_item() :void {
		$this->installController( false );

		$this->captureSubMenuItems();

		$licenseMenu = $this->findSubMenuCallBySlug( 'icwp-wpsf-license' );
		$this->assertIsArray( $licenseMenu );
		$this->assertSame(
			'<span class="shield_highlighted_menu">Get Pro Security</span>',
			$licenseMenu[ 'menu_title' ]
		);
		$this->assertStringContainsString( 'Get Pro Security', $licenseMenu[ 'page_title' ] );
	}

	public function test_premium_admin_submenu_does_not_add_free_upsell_license_item() :void {
		$this->installController( true );

		$this->captureSubMenuItems();

		$this->assertNull( $this->findSubMenuCallBySlug( 'icwp-wpsf-license' ) );
		$this->assertFalse(
			\str_contains(
				\implode( ' ', \array_column( $this->subMenuCalls, 'menu_title' ) ),
				'shield_highlighted_menu'
			)
		);
	}

	public function test_admin_menu_is_top_level_when_the_site_shows_it() :void {
		$calls = $this->captureAdminMenuRegistration( true );

		$this->assertSame( [ 'icwp-wpsf-plugin' ], \array_column( $calls[ 'menu' ], 'menu_slug' ) );
		$this->assertSame( [], $calls[ 'removed' ] );
		$this->assertSame( [], $calls[ 'management' ] );
		$this->assertArrayNotHasKey( 'title', $GLOBALS );
		$this->assertSame( [], $calls[ 'filters' ] );
	}

	public function test_hidden_top_level_menu_keeps_its_page_and_links_to_it_from_tools() :void {
		$calls = $this->captureAdminMenuRegistration( false );

		// The page is registered exactly as when shown, so its URL and screen are unchanged; only the item is hidden.
		$this->assertSame( [ 'icwp-wpsf-plugin' ], \array_column( $calls[ 'menu' ], 'menu_slug' ) );
		$this->assertSame( [ 'icwp-wpsf-plugin' ], $calls[ 'removed' ] );
		$this->assertSame( [ [
			'menu_slug'    => '/wp-admin/admin.php?page=icwp-wpsf-plugin',
			'capability'   => 'manage_options',
			'has_callback' => false,
		] ], $calls[ 'management' ] );
		// WordPress can't find the page's title, or open Tools, from a hidden menu item.
		$this->assertSame( 'Shield Security', $GLOBALS[ 'title' ] ?? null );
		$this->assertSame( [
			'parent_file'  => 'tools.php',
			'submenu_file' => '/wp-admin/admin.php?page=icwp-wpsf-plugin',
		], $calls[ 'filters' ] );
	}

	public function test_hidden_top_level_menu_leaves_other_admin_pages_alone() :void {
		$calls = $this->captureAdminMenuRegistration( false, 'some-other-page' );

		$this->assertCount( 1, $calls[ 'management' ] );
		$this->assertArrayNotHasKey( 'title', $GLOBALS );
		$this->assertSame( [], $calls[ 'filters' ] );
	}

	public function test_display_module_admin_page_passes_only_allowed_route_payload_to_page_render() :void {
		$importRenderData = $this->captureDisplayModuleRenderData( [
			Constants::NAV_ID                        => PluginNavs::NAV_TOOLS,
			Constants::NAV_SUB_ID                    => PluginNavs::SUBNAV_TOOLS_IMPORT,
			NetworkInviteRepository::REVIEW_QUERY_KEY => 'INVITE-ID',
			'unrelated'                             => 'drop-me',
		] );

		$this->assertSame( PageAdminPlugin::class, $importRenderData[ 'action' ] );
		$this->assertSame( [
			Constants::NAV_ID                        => PluginNavs::NAV_TOOLS,
			Constants::NAV_SUB_ID                    => PluginNavs::SUBNAV_TOOLS_IMPORT,
			NetworkInviteRepository::REVIEW_QUERY_KEY => 'invite-id',
		], $importRenderData[ 'action_data' ] );

		$nonImportRenderData = $this->captureDisplayModuleRenderData( [
			Constants::NAV_ID                        => PluginNavs::NAV_REPORTS,
			Constants::NAV_SUB_ID                    => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
			NetworkInviteRepository::REVIEW_QUERY_KEY => 'INVITE-ID',
		] );

		$this->assertSame( PageAdminPlugin::class, $nonImportRenderData[ 'action' ] );
		$this->assertSame( [
			Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
		], $nonImportRenderData[ 'action_data' ] );
	}

	private function captureSubMenuItems() :void {
		( new PluginAdminPageHandlerTestSubject( [
			Constants::NAV_ID => PluginNavs::NAV_DASHBOARD,
		] ) )->captureSubMenuItems();
	}

	/**
	 * @return array{
	 *   menu:list<array<string,mixed>>,
	 *   removed:list<string>,
	 *   management:list<array<string,mixed>>,
	 *   filters:array<string,mixed>
	 * }
	 */
	private function captureAdminMenuRegistration( bool $showsTopLevelMenu, string $page = 'icwp-wpsf-plugin' ) :array {
		$calls = [ 'menu' => [], 'removed' => [], 'management' => [], 'filters' => [] ];
		foreach ( [ 'parent_file', 'submenu_file' ] as $filter ) {
			Filters\expectAdded( $filter )->zeroOrMoreTimes()->whenHappen( function ( callable $callback ) use ( &$calls, $filter ) :void {
				$calls[ 'filters' ][ $filter ] = $callback( 'unchanged' );
			} );
		}
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'add_menu_page' )->alias( function ( string $pageTitle, string $menuTitle, string $capability, string $menuSlug ) use ( &$calls ) :string {
			$calls[ 'menu' ][] = [ 'menu_slug' => $menuSlug, 'capability' => $capability ];
			return 'toplevel_page_'.$menuSlug;
		} );
		Functions\when( 'remove_menu_page' )->alias( function ( string $menuSlug ) use ( &$calls ) {
			$calls[ 'removed' ][] = $menuSlug;
			return [ 'Shield Security', 'manage_options', $menuSlug ];
		} );
		Functions\when( 'add_management_page' )->alias( function ( string $pageTitle, string $menuTitle, string $capability, string $menuSlug, $callback = '' ) use ( &$calls ) :string {
			$calls[ 'management' ][] = [
				'menu_slug'    => $menuSlug,
				'capability'   => $capability,
				'has_callback' => !empty( $callback ),
			];
			return 'tools_page_'.$menuSlug;
		} );
		Actions\expectAdded( 'admin_menu' )->once()->whenHappen( static function ( callable $callback ) :void {
			$callback();
		} );

		$this->installController( false, null, $showsTopLevelMenu );
		$this->servicesSnapshot = ServicesState::snapshot();
		$request = new Request();
		$request->query = [ 'page' => $page ];
		ServicesState::installItems( [
			'service_wpgeneral' => new General(),
			'service_request'   => $request,
		] );
		( new PluginAdminPageHandlerTestSubject( [
			Constants::NAV_ID => PluginNavs::NAV_DASHBOARD,
		] ) )->runExec();

		return $calls;
	}

	private function installController( bool $isPremium, ?UnitTestActionRouter $actionRouter = null, bool $showsTopLevelMenu = true ) :void {
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			$actionRouter,
			(object)[
				'cfg' => (object)[
					'menu'       => [
						'show'           => true,
						'top_level'      => true,
						'has_submenu'    => true,
						'do_submenu_fix' => false,
					],
					'properties' => [
						'slug_parent'      => 'icwp',
						'slug_plugin'      => 'wpsf',
						'base_permissions' => 'manage_options',
					],
				],
				'labels' => (object)[
					'Name'          => 'Shield Security',
					'MenuTitle'     => 'Shield Security',
					'icon_url_16x16' => 'dashicons-shield',
				],
				'comps' => (object)[
					'license'     => new UnitTestLicenseComponent( $isPremium ),
					'zones'       => new UnitTestZonesComponent(),
					'opts_lookup' => new class( $showsTopLevelMenu ) {

						private bool $showsTopLevelMenu;

						public function __construct( bool $showsTopLevelMenu ) {
							$this->showsTopLevelMenu = $showsTopLevelMenu;
						}

						public function showsInDashboard( string $element ) :bool {
							return $element !== EnumDashboardDisplay::ADMIN_MENU || $this->showsTopLevelMenu;
						}
					},
				],
			]
		);
	}

	private function captureDisplayModuleRenderData( array $actionData ) :array {
		$capture = new RenderCapture();
		$this->installController( true, new UnitTestActionRouter( $capture ) );

		$level = \ob_get_level();
		\ob_start();
		try {
			( new PluginAdminPageHandler( $actionData ) )->displayModuleAdminPage();
		}
		finally {
			while ( \ob_get_level() > $level ) {
				\ob_end_clean();
			}
		}

		$this->assertCount( 1, $capture->calls );
		return $capture->calls[ 0 ];
	}

	private function findSubMenuCallBySlug( string $menuSlug ) :?array {
		foreach ( $this->subMenuCalls as $subMenuCall ) {
			if ( $subMenuCall[ 'menu_slug' ] === $menuSlug ) {
				return $subMenuCall;
			}
		}
		return null;
	}
}

class PluginAdminPageHandlerTestSubject extends PluginAdminPageHandler {

	public function captureSubMenuItems() :void {
		$this->addSubMenuItems();
	}

	public function runExec() :void {
		$this->exec();
	}
}

}

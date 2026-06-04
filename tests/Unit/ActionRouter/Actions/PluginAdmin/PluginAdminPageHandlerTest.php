<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Actions\PluginAdmin {

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin\PluginAdminPageHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestLicenseComponent,
	UnitTestPluginUrls
};

class PluginAdminPageHandlerTest extends BaseUnitTest {

	private array $subMenuCalls = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
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

	private function captureSubMenuItems() :void {
		( new PluginAdminPageHandlerTestSubject( [
			Constants::NAV_ID => PluginNavs::NAV_DASHBOARD,
		] ) )->captureSubMenuItems();
	}

	private function installController( bool $isPremium ) :void {
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'cfg' => (object)[
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
					'license' => new UnitTestLicenseComponent( $isPremium ),
				],
			]
		);
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
}

}

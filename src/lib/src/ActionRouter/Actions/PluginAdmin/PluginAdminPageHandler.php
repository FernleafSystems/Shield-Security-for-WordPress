<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\{
	NonceVerifyNotRequired,
	SecurityAdminNotRequired
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AssetsCustomizer;
use FernleafSystems\Wordpress\Services\Services;

class PluginAdminPageHandler extends Actions\BaseAction {

	use NonceVerifyNotRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_admin_page_handler';

	protected $pageHookSuffix;

	protected $screenID;

	protected function exec() {
		if ( ( is_admin() || is_network_admin() ) && !Services::WpGeneral()->isAjax() ) {

			if ( apply_filters( 'shield/show_admin_menu', self::con()->cfg->menu[ 'show' ] ?? true ) ) {
				add_action( 'admin_menu', function () {
					$this->createAdminMenu();
				} );
				add_action( 'network_admin_menu', function () {
					$this->createNetworkAdminMenu();
				} );
			}

			( new AssetsCustomizer() )->execute();
		}
	}

	private function createAdminMenu() {
		$con = self::con();
		$menu = $con->cfg->menu;

		if ( $menu[ 'top_level' ] ) {

			$this->pageHookSuffix = add_menu_page(
				$con->getHumanName(),
				$con->labels->MenuTitle,
				$con->cfg->properties[ 'base_permissions' ],
				$this->getPrimaryMenuSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$con->labels->icon_url_16x16
			);

			if ( $menu[ 'has_submenu' ] ) {
				$this->addSubMenuItems();
			}

			if ( $menu[ 'do_submenu_fix' ] ) {
				global $submenu;
				$menuID = $this->getPrimaryMenuSlug();
				if ( isset( $submenu[ $menuID ] ) ) {
//					$submenu[ $menuID ][ 0 ][ 0 ] = __( 'Security Dashboard', 'wp-simple-firewall' );
					unset( $submenu[ $menuID ][ 0 ] );
				}
				else {
					// remove entire top-level menu if no submenu items - ASSUMES this plugin MUST have submenu or no menu at all
					remove_menu_page( $menuID );
				}
			}
		}
	}

	private function createNetworkAdminMenu() {
		$this->createAdminMenu();
	}

	protected function addSubMenuItems() {
		$con = self::con();

		$navs = [
			PluginNavs::NAV_DASHBOARD      => __( 'Security Dashboard', 'wp-simple-firewall' ),
			PluginNavs::NAV_REPORTS        => __( 'Reports', 'wp-simple-firewall' ),
			PluginNavs::NAV_IPS            => __( 'IP Manager', 'wp-simple-firewall' ),
			PluginNavs::NAV_SCANS          => __( 'Scans', 'wp-simple-firewall' ),
			PluginNavs::NAV_ACTIVITY       => __( 'Activity', 'wp-simple-firewall' ),
			PluginNavs::NAV_TRAFFIC        => __( 'Traffic', 'wp-simple-firewall' ),
			PluginNavs::NAV_OPTIONS_CONFIG => __( 'Configuration', 'wp-simple-firewall' ),
		];
		if ( !self::con()->isPremiumActive() ) {
			$navs[ PluginNavs::NAV_LICENSE ] = sprintf( '<span class="shield_highlighted_menu">%s</span>', 'ShieldPRO' );
		}

		$currentNav = (string)Services::Request()->query( Constants::NAV_ID );
		foreach ( $navs as $submenuNavID => $submenuTitle ) {

			$markupTitle = sprintf( '<span style="color:#fff;font-weight: 600">%s</span>', $submenuTitle );
			$doMarkupTitle = $currentNav === $submenuNavID;

			add_submenu_page(
				$this->getPrimaryMenuSlug(),
				sprintf( '%s | %s', $submenuTitle, self::con()->getHumanName() ),
				$doMarkupTitle ? $markupTitle : $submenuTitle,
				$con->cfg->properties[ 'base_permissions' ],
				$con->prefix( $submenuNavID ),
				[ $this, 'displayModuleAdminPage' ]
			);
		}
	}

	public function displayModuleAdminPage() {
		echo self::con()->action_router->render( Actions\Render\PageAdminPlugin::SLUG );
	}

	private function getPrimaryMenuSlug() :string {
		return self::con()->getModule_Plugin()->getModSlug();
	}
}
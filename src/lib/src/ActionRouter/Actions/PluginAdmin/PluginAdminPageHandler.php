<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\{
	NonceVerifyNotRequired,
	SecurityAdminNotRequired
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PluginAdminPageHandler extends Actions\BaseAction {

	use NonceVerifyNotRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_admin_page_handler';

	protected $pageHookSuffix;

	protected $screenID;

	protected function exec() {
		if ( !Services::WpGeneral()->isAjax()
			 && apply_filters( 'shield/show_admin_menu', self::con()->cfg->menu[ 'show' ] ?? true ) ) {

			add_action( 'admin_menu', function () {
				if ( !Services::WpGeneral()->isMultisite() && is_admin() ) {
					$this->createAdminMenu();
				}
			} );

			add_action( 'network_admin_menu', function () {
				if ( Services::WpGeneral()->isMultisite() && is_network_admin() && is_main_network() ) {
					$this->createNetworkAdminMenu();
				}
			} );

			add_filter( 'nocache_headers', [ $this, 'adjustNocacheHeaders' ] );
		}
	}

	/**
	 * In order to prevent certain errors when the back button is used
	 * @param array $h
	 * @return array
	 */
	public function adjustNocacheHeaders( $h ) {
		if ( \is_array( $h ) && !empty( $h[ 'Cache-Control' ] ) && self::con()->isPluginAdminPageRequest() ) {
			$Hs = \array_map( '\trim', \explode( ',', $h[ 'Cache-Control' ] ) );
			$Hs[] = 'no-store';
			$h[ 'Cache-Control' ] = \implode( ', ', \array_unique( $Hs ) );
		}
		return $h;
	}

	private function createAdminMenu() {
		$con = self::con();
		$menu = $con->cfg->menu;

		if ( $menu[ 'top_level' ] ) {

			$this->pageHookSuffix = add_menu_page(
				$con->labels->Name,
				$con->labels->MenuTitle,
				$con->cfg->properties[ 'base_permissions' ],
				$con->plugin_urls->rootAdminPageSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$con->labels->icon_url_16x16
			);

			if ( $menu[ 'has_submenu' ] ) {
				$this->addSubMenuItems();
			}

			if ( $menu[ 'do_submenu_fix' ] ) {
				global $submenu;
				$menuID = $con->plugin_urls->rootAdminPageSlug();
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
			PluginNavs::NAV_DASHBOARD => __( 'Security Dashboard', 'wp-simple-firewall' ),
			PluginNavs::NAV_ZONES     => __( 'Security Zones', 'wp-simple-firewall' ),
			PluginNavs::NAV_IPS       => __( 'IP Manager', 'wp-simple-firewall' ),
			PluginNavs::NAV_SCANS     => __( 'Scans', 'wp-simple-firewall' ),
			PluginNavs::NAV_ACTIVITY  => __( 'Activity', 'wp-simple-firewall' ),
			PluginNavs::NAV_TRAFFIC   => __( 'Traffic', 'wp-simple-firewall' ),
			PluginNavs::NAV_RULES     => __( 'Custom Rules', 'wp-simple-firewall' ),
			PluginNavs::NAV_REPORTS   => __( 'Reports', 'wp-simple-firewall' ),
		];
		if ( !self::con()->isPremiumActive() ) {
			$navs[ PluginNavs::NAV_LICENSE ] = sprintf( '<span class="shield_highlighted_menu">%s</span>', 'ShieldPRO' );
		}

		$currentNav = $this->action_data[ Constants::NAV_ID ] ?? '';
		foreach ( $navs as $submenuNavID => $submenuTitle ) {

			$markupTitle = sprintf( '<span style="color:#fff;font-weight: 600">%s</span>', $submenuTitle );
			$doMarkupTitle = $currentNav === $submenuNavID;

			add_submenu_page(
				$con->plugin_urls->rootAdminPageSlug(),
				sprintf( '%s | %s', $submenuTitle, $con->labels->Name ),
				$doMarkupTitle ? $markupTitle : $submenuTitle,
				$con->cfg->properties[ 'base_permissions' ],
				$con->prefix( $submenuNavID ),
				[ $this, 'displayModuleAdminPage' ]
			);
		}
	}

	public function displayModuleAdminPage() {
		echo self::con()->action_router->render( Actions\Render\PageAdminPlugin::class );
	}
}
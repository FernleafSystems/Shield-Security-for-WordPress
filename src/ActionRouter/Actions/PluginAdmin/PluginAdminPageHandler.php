<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumDashboardDisplay;
use FernleafSystems\Wordpress\Services\Services;

class PluginAdminPageHandler extends Actions\BaseAction {
	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_admin_page_handler';

	/**
	 * Runs only for non-AJAX admin requests; the controller decides that before calling it.
	 */
	protected function exec() {
		if ( apply_filters( 'shield/show_admin_menu', self::con()->cfg->menu[ 'show' ] ?? true ) ) {

			add_action( 'admin_menu', function () {
				if ( !Services::WpGeneral()->isMultisite() && is_admin() ) {
					$this->createAdminMenu();
					if ( !self::con()->comps->opts_lookup->showsInDashboard( EnumDashboardDisplay::ADMIN_MENU ) ) {
						$this->moveAdminMenuToTools();
					}
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
	 * @param array|mixed $h
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

			add_menu_page(
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
					unset( $submenu[ $menuID ][ 0 ] );
				}
				else {
					// remove entire top-level menu if no submenu items - ASSUMES this plugin MUST have submenu or no menu at all
					remove_menu_page( $menuID );
				}
			}
		}
	}

	/**
	 * The top-level page stays registered, so Shield keeps its one URL; only its menu item is hidden. A Tools entry
	 * without a callback is a plain link, so it points straight at that URL.
	 */
	private function moveAdminMenuToTools() :void {
		$con = self::con();
		if ( remove_menu_page( $con->plugin_urls->rootAdminPageSlug() ) !== false ) {

			$url = $con->plugin_urls->rootAdminPage();
			add_management_page(
				$con->labels->Name,
				$con->labels->MenuTitle,
				$con->cfg->properties[ 'base_permissions' ],
				$url
			);

			if ( $con->isPluginAdminPageRequest() ) {
				// WordPress finds a page's title, and its place in the menu, from its menu item, which is now hidden.
				$GLOBALS[ 'title' ] = $con->labels->Name;
				add_filter( 'parent_file', fn() => 'tools.php' );
				add_filter( 'submenu_file', fn() => $url );
			}
		}
	}

	/**
	 * The network admin has no Tools menu, so the top-level menu stays there.
	 */
	private function createNetworkAdminMenu() {
		$this->createAdminMenu();
	}

	protected function addSubMenuItems() {
		$con = self::con();

		$navs = [
			PluginNavs::NAV_DASHBOARD => __( 'Dashboard', 'wp-simple-firewall' ),
			PluginNavs::NAV_SCANS     => __( 'Actions Queue', 'wp-simple-firewall' ),
			PluginNavs::NAV_ACTIVITY  => __( 'Investigate', 'wp-simple-firewall' ),
			PluginNavs::NAV_ZONES     => __( 'Configure', 'wp-simple-firewall' ),
			PluginNavs::NAV_REPORTS   => __( 'Reports', 'wp-simple-firewall' ),
		];
		if ( !self::con()->isPremiumActive() ) {
			$navs[ PluginNavs::NAV_LICENSE ] = sprintf(
				'<span class="shield_highlighted_menu">%s</span>',
				__( 'Get Pro Security', 'wp-simple-firewall' )
			);
		}

		$currentNav = ActionData::TransportField( $this->action_data, Constants::NAV_ID );
		if ( !isset( $navs[ $currentNav ] ) ) {
			$mode = PluginNavs::modeForNav( $currentNav );
			$entry = PluginNavs::defaultEntryForMode( $mode );
			$currentNav = $entry[ 'nav' ];
		}
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
		echo self::con()->action_router->render(
			Actions\Render\PageAdminPlugin::class,
			( new Actions\Render\PageAdminPluginRouteResolver() )->buildAdminPageActionData( $this->action_data )
		);
	}
}

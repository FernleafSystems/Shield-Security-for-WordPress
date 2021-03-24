<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class MainAdminMenu {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = $this->getCon();
		return $con->isValidAdminArea()
			   && apply_filters( 'shield/show_admin_menu', $con->cfg->menu[ 'show' ] ?? true );
	}

	protected function run() {
		add_action( 'admin_menu', function () {
			$this->createAdminMenu();
		} );
		add_action( 'network_admin_menu', function () {
			$this->createAdminMenu();
		} );
	}

	private function createAdminMenu() {
		$con = $this->getCon();

		$menu = $con->cfg->menu;
		if ( $menu[ 'top_level' ] ) {

			$labels = $con->getLabels();
			$menuTitle = empty( $labels[ 'MenuTitle' ] ) ? $menu[ 'title' ] : $labels[ 'MenuTitle' ];
			if ( is_null( $menuTitle ) ) {
				$menuTitle = $con->getHumanName();
			}

			$sMenuIcon = $con->urls->forImage( $menu[ 'icon_image' ] );
			$sIconUrl = empty( $labels[ 'icon_url_16x16' ] ) ? $sMenuIcon : $labels[ 'icon_url_16x16' ];

			$parentMenuID = $con->getPluginPrefix();
			add_menu_page(
				$con->getHumanName(),
				$menuTitle,
				$con->getBasePermissions(),
				$parentMenuID,
				[ $this, 'onDisplayTopMenu' ],
				$sIconUrl
			);

			if ( $menu[ 'has_submenu' ] ) {

				$menuItems = apply_filters( $con->prefix( 'submenu_items' ), [] );
				if ( !empty( $menuItems ) ) {
					foreach ( $menuItems as $menuTitle => $menuItem ) {
						list( $sMenuItemText, $sMenuItemId, $aMenuCallBack, $bShowItem ) = $menuItem;
						add_submenu_page(
							$bShowItem ? $parentMenuID : null,
							$menuTitle,
							$sMenuItemText,
							$con->getBasePermissions(),
							$sMenuItemId,
							$aMenuCallBack
						);
					}
				}
			}

			if ( $menu[ 'do_submenu_fix' ] ) {
				$this->fixSubmenu();
			}
		}
	}

	public function onDisplayTopMenu() {
	}

	private function fixSubmenu() {
		global $submenu;
		$menuID = $this->getCon()->getPluginPrefix();
		if ( isset( $submenu[ $menuID ] ) ) {
			unset( $submenu[ $menuID ][ 0 ] );
		}
	}
}

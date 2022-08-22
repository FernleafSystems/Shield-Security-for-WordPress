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

			$parentMenuID = $con->getPluginPrefix();
			add_menu_page(
				$con->getHumanName(),
				$con->labels->MenuTitle,
				'read',
				$parentMenuID,
				[ $this, 'onDisplayTopMenu' ],
				$con->labels->icon_url_16x16
			);

			if ( $menu[ 'has_submenu' ] ) {
				do_action( $con->prefix( 'admin_submenu' ) );
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
		$con = $this->getCon();
		$menuID = $con->getPluginPrefix();
		if ( isset( $submenu[ $menuID ] ) ) {
			unset( $submenu[ $menuID ][ 0 ] );
		}
		else {
			// remove entire top-level menu if no submenu items - ASSUMES this plugin MUST have submenu or no menu at all
			remove_menu_page( $menuID );
		}
	}
}
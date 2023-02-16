<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AdminBarMenu {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = $this->getCon();
		return $con->getMeetsBasePermissions() &&
			   apply_filters( 'shield/show_admin_bar_menu', $con->cfg->properties[ 'show_admin_bar_menu' ] );
	}

	protected function run() {
		add_action( 'admin_bar_menu', function ( $adminBar ) {
			if ( $adminBar instanceof \WP_Admin_Bar ) {
				$this->createAdminBarMenu( $adminBar );
			}
		}, 100 );
	}

	private function createAdminBarMenu( \WP_Admin_Bar $adminBar ) {
		$con = $this->getCon();

		$groups = array_filter( apply_filters( $con->prefix( 'admin_bar_menu_groups' ), [] ) );
		$totalWarnings = 0;

		if ( !empty( $groups ) ) {

			$topNodeID = $con->prefix( 'adminbarmenu' );

			foreach ( $groups as $key => $group ) {

				$group[ 'id' ] = $con->prefix( 'adminbarmenu-sub'.$key );

				foreach ( $group[ 'items' ] as $item ) {
					$totalWarnings += $item[ 'warnings' ] ?? 0;
					$item[ 'parent' ] = $group[ 'id' ];
					$adminBar->add_node( $item );
				}

				unset( $group[ 'items' ] );
				$group[ 'parent' ] = $topNodeID;
				$adminBar->add_node( $group );
			}

			// The top menu item.
			$adminBar->add_node( [
				'id'    => $topNodeID,
				'title' => sprintf( '%s %s', $con->getHumanName(),
					empty( $totalWarnings ) ? '' : sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $totalWarnings )
				),
				'href'  => $con->plugin_urls->adminHome()
			] );
		}
	}
}
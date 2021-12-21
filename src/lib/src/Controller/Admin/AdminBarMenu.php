<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AdminBarMenu {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = $this->getCon();
		return $con->isValidAdminArea( true ) &&
			   apply_filters( $con->prefix( 'shield/show_admin_bar_menu' ), $con->cfg->properties[ 'show_admin_bar_menu' ] );
	}

	protected function run() {
		add_action( 'admin_bar_menu', function ( $adminBar ) {
			$this->createAdminBarMenu( $adminBar );
		}, 100 );
	}

	/**
	 * @param \WP_Admin_Bar $adminBar
	 */
	private function createAdminBarMenu( $adminBar ) {
		$con = $this->getCon();

		$items = apply_filters( $con->prefix( 'admin_bar_menu_items' ), [] );
		if ( !empty( $items ) && is_array( $items ) ) {
			$warningCount = 0;
			foreach ( $items as $item ) {
				$warningCount += $item[ 'warnings' ] ?? 0;
			}

			$nodeId = $con->prefix( 'adminbarmenu' );
			$adminBar->add_node( [
				'id'    => $nodeId,
				'title' => $con->getHumanName()
						   .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $warningCount ),
			] );
			foreach ( $items as $item ) {
				$item[ 'parent' ] = $nodeId;
				$adminBar->add_menu( $item );
			}
		}
	}
}
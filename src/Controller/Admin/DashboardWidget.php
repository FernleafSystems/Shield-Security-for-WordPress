<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DashboardWidget {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = self::con();
		return $con->isValidAdminArea() &&
			   Services::WpUsers()->isUserAdmin() &&
			   apply_filters( 'shield/show_dashboard_widget', $con->cfg->properties[ 'show_dashboard_widget' ] ?? true );
	}

	protected function run() {
		add_action( 'wp_dashboard_setup', function () {
			$this->createWidget();
		} );
	}

	private function createWidget() {
		$con = self::con();
		wp_add_dashboard_widget(
			$con->prefix( 'dashboard_widget' ),
			apply_filters( 'shield/dashboard_widget_title', sprintf( '%s: %s', $con->labels->Name, __( 'Actions Queue', 'wp-simple-firewall' ) ) ),
			function () {
				echo sprintf(
					'<div id="ShieldDashboardWidget" aria-busy="true"><div class="shield-dashboard-widget__loading">%s...</div></div>',
					esc_html__( 'Loading', 'wp-simple-firewall' )
				);
			}
		);
	}
}

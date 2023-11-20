<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginReinstall;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ReinstallDialog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Services\Services;

class PtgAddReinstallLinks {

	use Controller\ScanControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$scanCon = $this->getScanController();
		return $scanCon->isReady()
			   && $scanCon->con()->isPremiumActive()
			   && $scanCon->opts()->isOpt( 'ptg_reinstall_links', 'Y' );
	}

	protected function run() {
		add_action( 'plugin_action_links', function ( $links, $file ) {
			$con = $this->getScanController()->con();
			if ( $con->this_req->is_security_admin && \is_array( $links ) && \is_string( $file ) ) {
				$links = $this->addActionLinkRefresh( $links, $file );
			}
			return $links;
		}, 50, 2 );

		add_action( 'admin_footer', function () {
			$con = $this->getScanController()->con();
			if ( $con->this_req->is_security_admin && $con->action_router ) {
				echo $con->action_router->render( ReinstallDialog::SLUG );
			}
		} );

		add_filter( 'shield/custom_localisations/components', function ( array $components, string $hook ) {
			$components[ 'plugin_reinstall' ] = [
				'key'      => 'plugin_reinstall',
				'required' => $hook === 'plugins.php' && $this->getScanController()->con()->this_req->is_security_admin,
				'handles'  => [
					'wpadmin',
				],
				'data'     => function () {
					return [
						'ajax' => [
							'plugin_reinstall' => ActionData::Build( PluginReinstall::class ),
						],
						'vars' => [
							'reinstallable' => Services::WpPlugins()->getInstalledWpOrgPluginFiles(),
						]
					];
				},
			];
			return $components;
		}, 10, 2 );
	}

	private function addActionLinkRefresh( array $links, string $file ) :array {
		$plugin = Services::WpPlugins()->getPluginAsVo( $file );
		if ( !empty( $plugin ) && $plugin->asset_type === 'plugin'
			 && $plugin->isWpOrg() && !Services::WpPlugins()->isUpdateAvailable( $file ) ) {
			$links[ 'shield-reinstall' ] = sprintf( '<a href="javascript:void(0)">%s</a>', __( 'Re-Install', 'wp-simple-firewall' ) );
		}
		return $links;
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginReinstall;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ReinstallDialog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PtgAddReinstallLinks {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return self::con()->isValidAdminArea( true ) && $this->opts()->isOpt( 'ptg_reinstall_links', 'Y' );
	}

	protected function run() {
		add_action(
			is_network_admin() ? 'network_admin_plugin_action_links' : 'plugin_action_links',
			function ( $links, $file ) {
				global $hook_suffix;
				if ( 'plugins.php' === $hook_suffix && \is_array( $links ) && \is_string( $file ) ) {
					$con = \method_exists( $this, 'con' ) ? self::con() : $this->getScanController()->con();
					if ( $con->this_req->is_security_admin ) {
						$plugin = Services::WpPlugins()->getPluginAsVo( $file );
						if ( !empty( $plugin ) && $plugin->asset_type === 'plugin'
							 && $plugin->isWpOrg() && !Services::WpPlugins()->isUpdateAvailable( $file ) ) {
							$links[ 'shield-reinstall' ] = sprintf( '<a href="javascript:void(0)">%s</a>', __( 'Re-Install', 'wp-simple-firewall' ) );
						}
					}
				}
				return $links;
			},
			50, 2
		);

		add_action( 'admin_footer', function ( $hook_suffix_arg ) {
			global $hook_suffix;
			if ( \in_array( 'plugins.php', [ $hook_suffix_arg, $hook_suffix ] ) ) {
				$con = \method_exists( $this, 'con' ) ? self::con() : $this->getScanController()->con();
				if ( $con->this_req->is_security_admin ) {
					echo $con->action_router->render( ReinstallDialog::SLUG );
				}
			}
		} );

		add_filter( 'shield/custom_localisations/components', function ( array $components, string $hook ) {
			$con = \method_exists( $this, 'con' ) ? self::con() : $this->getScanController()->con();
			$components[ 'plugin_reinstall' ] = [
				'key'      => 'plugin_reinstall',
				'required' => $hook === 'plugins.php' && $con->this_req->is_security_admin,
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

	/**
	 * @deprecated 18.6
	 */
	private function addActionLinkRefresh( array $links, string $file ) :array {
		return $links;
	}
}
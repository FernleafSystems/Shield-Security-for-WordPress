<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\HackGuardPluginReinstall;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ReinstallDialog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Services\Services;

class PtgAddReinstallLinks {

	use Controller\ScanControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$scanCon = $this->getScanController();
		/** @var HackGuard\Options $opts */
		$opts = $scanCon->getOptions();
		return $scanCon->isReady() && $opts->isPtgReinstallLinks();
	}

	protected function run() {
		add_action( 'plugin_action_links', function ( $links, $file ) {
			if ( is_array( $links ) && is_string( $file ) ) {
				$links = $this->addActionLinkRefresh( $links, $file );
			}
			return $links;
		}, 50, 2 );

		add_action( 'admin_footer', function () {
			$con = $this->getScanController()->getCon();
			if ( $con->action_router ) {
				echo $con->action_router->render( ReinstallDialog::SLUG );
			}
		} );

		add_filter( 'shield/custom_localisations', function ( array $localz, $hook ) {
			if ( in_array( $hook, [ 'plugins.php', ] ) ) {
				$localz[] = [
					'global-plugin',
					'icwp_wpsf_vars_hp',
					[
						'ajax_plugin_reinstall' => ActionData::Build( HackGuardPluginReinstall::SLUG ),
						'reinstallable'         => Services::WpPlugins()->getInstalledWpOrgPluginFiles(),
						'strings'               => [
							'reinstall_first' => __( 'Re-install First', 'wp-simple-firewall' )
												 .'. '.__( 'Then Activate', 'wp-simple-firewall' ),
							'okay_reinstall'  => sprintf( '%s, %s',
								__( 'Yes', 'wp-simple-firewall' ), __( 'Re-Install It', 'wp-simple-firewall' ) ),
							'activate_only'   => __( 'Activate Only', 'wp-simple-firewall' ),
							'cancel'          => __( 'Cancel', 'wp-simple-firewall' ),
						]
					]
				];
			}
			return $localz;
		}, 10, 2 );
	}

	private function addActionLinkRefresh( array $links, string $file ) :array {
		$WPP = Services::WpPlugins();

		$plugin = $WPP->getPluginAsVo( $file );
		if ( !empty( $plugin ) && $plugin->asset_type === 'plugin'
			 && $plugin->isWpOrg() && !$WPP->isUpdateAvailable( $file ) ) {
			$template = '<a href="javascript:void(0)">%s</a>';
			$links[ 'icwp-reinstall' ] = sprintf( $template, __( 'Re-Install', 'wp-simple-firewall' ) );
		}

		return $links;
	}

	/**
	 * @deprecated 17.0
	 */
	private function printPluginReinstallDialogs() {
	}
}
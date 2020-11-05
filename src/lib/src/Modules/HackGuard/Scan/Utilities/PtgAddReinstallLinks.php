<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class PtgAddReinstallLinks {

	use Controller\ScanControllerConsumer;
	use OneTimeExecute;

	/**
	 * @var int
	 */
	private $nColumnsCount;

	/**
	 * @var int
	 */
	private $vulnCount;

	protected function run() {
		add_action( 'plugin_action_links', function ( $links, $file ) {
			if ( is_array( $links ) && is_string( $file ) ) {
				$links = $this->addActionLinkRefresh( $links, $file );
			}
			return $links;
		}, 50, 2 );
		add_action( 'admin_footer', function () {
			$this->printPluginReinstallDialogs();
		} );
	}

	protected function canRun() {
		$scanCon = $this->getScanController();
		/** @var HackGuard\Options $opts */
		$opts = $scanCon->getOptions();
		return $scanCon->isEnabled() && $opts->isPtgReinstallLinks();
	}

	private function addActionLinkRefresh( array $links, string $file ) :array {
		$WPP = Services::WpPlugins();

		$plugin = $WPP->getPluginAsVo( $file );
		if ( $plugin instanceof WpPluginVo && $plugin->isWpOrg() && !$WPP->isUpdateAvailable( $file ) ) {
			$template = '<a href="javascript:void(0)">%s</a>';
			$links[ 'icwp-reinstall' ] = sprintf( $template, __( 'Re-Install', 'wp-simple-firewall' ) );
		}

		return $links;
	}

	private function printPluginReinstallDialogs() {
		$scanCon = $this->getScanController();
		echo $scanCon->getMod()
					 ->renderTemplate(
						 'snippets/dialog_plugins_reinstall.twig',
						 [
							 'strings'     => [
								 'are_you_sure'       => __( 'Are you sure?', 'wp-simple-firewll' ),
								 'really_reinstall'   => __( 'Really Re-Install Plugin', 'wp-simple-firewll' ),
								 'wp_reinstall'       => __( 'WordPress will now download and install the latest available version of this plugin.', 'wp-simple-firewll' ),
								 'in_case'            => sprintf( '%s: %s',
									 __( 'Note', 'wp-simple-firewall' ),
									 __( 'In case of possible failure, it may be better to do this while the plugin is inactive.', 'wp-simple-firewll' )
								 ),
								 'reinstall_first'    => __( 'Re-install first?', 'wp-simple-firewall' ),
								 'corrupted'          => __( "This ensures files for this plugin haven't been corrupted in any way.", 'wp-simple-firewall' ),
								 'choose'             => __( "You can choose to 'Activate Only' (not recommended), or close this message to cancel activation.", 'wp-simple-firewall' ),
								 'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
								 'download'           => sprintf(
									 __( 'For best security practices, %s will download and re-install the latest available version of this plugin.', 'wp-simple-firewall' ),
									 $scanCon->getHumanName()
								 )
							 ],
							 'js_snippets' => []
						 ],
						 true
					 );
	}
}
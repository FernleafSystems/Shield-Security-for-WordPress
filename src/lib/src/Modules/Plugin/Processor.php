<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginTelemetry;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Options\CleanStorage;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function run() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->removePluginConflicts();
		( new Lib\OverrideLocale() )
			->setMod( $this->getMod() )
			->run();

		$mod->getShieldNetApiController()->execute();
		$mod->getPluginBadgeCon()->execute();

		( new PluginTelemetry() )
			->setMod( $this->getMod() )
			->execute();

		if ( $opts->isOpt( 'importexport_enable', 'Y' ) ) {
			$mod->getImpExpController()->execute();
		}

		add_filter( $con->prefix( 'delete_on_deactivate' ), function ( $isDelete ) use ( $opts ) {
			return $isDelete || $opts->isOpt( 'delete_on_deactivate', 'Y' );
		} );

		add_action( $con->prefix( 'dashboard_widget_content' ), function () {
			$this->printDashboardWidget();
		}, 11 );
	}

	private function printDashboardWidget() {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$labels = $con->getLabels();

		echo $this->getMod()->renderTemplate(
			'snippets/widget_dashboard_plugin.php',
			[
				'install_days' => sprintf( __( 'Days Installed: %s', 'wp-simple-firewall' ), $opts->getInstallationDays() ),
				'footer'       => sprintf( __( '%s is provided by %s', 'wp-simple-firewall' ), $con->getHumanName(),
					sprintf( '<a href="%s" target="_blank">%s</a>', $labels[ 'AuthorURI' ], $labels[ 'Author' ] )
				),
				'ip_address'   => sprintf( __( 'Your IP address is: %s', 'wp-simple-firewall' ),
					Services::IP()->getRequestIp() )
			]
		);
	}

	public function runDailyCron() {
		$this->getCon()->fireEvent( 'test_cron_run' );
		( new CleanStorage() )
			->setCon( $this->getCon() )
			->run();
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', [ $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ], 0 );
		}
	}
}
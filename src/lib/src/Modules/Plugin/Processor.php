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

		$this->removePluginConflicts();
		( new Lib\OverrideLocale() )
			->setMod( $this->getMod() )
			->run();

		$mod->getShieldNetApiController()->execute();
		$mod->getPluginBadgeCon()->execute();

		( new PluginTelemetry() )
			->setMod( $this->getMod() )
			->execute();

		if ( $this->getOptions()->isOpt( 'importexport_enable', 'Y' ) ) {
			$mod->getImpExpController()->execute();
		}

		add_filter( $con->prefix( 'delete_on_deactivate' ), function ( $isDelete ) {
			return $isDelete || $this->getOptions()->isOpt( 'delete_on_deactivate', 'Y' );
		} );

		add_action( $con->prefix( 'dashboard_widget_content' ), function () {
		}, 11 );
	}

	/**
	 * @deprecated 15.0
	 */
	private function printDashboardWidget() {
		echo '';
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
		if ( @function_exists( '\wp_cache_setting' ) ) {
			@wp_cache_setting( 'wp_super_cache_late_init', 1 );
		}
	}
}
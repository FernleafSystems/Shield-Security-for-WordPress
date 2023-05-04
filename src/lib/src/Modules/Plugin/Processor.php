<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Options\CleanStorage;

class Processor extends BaseShield\Processor {

	public const MOD = ModCon::SLUG;

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->mod();

		$this->removePluginConflicts();
		( new Lib\OverrideLocale() )->execute();

		$mod->getShieldNetApiController()->execute();
		$mod->getPluginBadgeCon()->execute();

		( new Lib\AllowBetaUpgrades() )->execute();
		( new Lib\SiteHealthController() )->execute();

		if ( $this->getOptions()->isOpt( 'importexport_enable', 'Y' ) ) {
			$mod->getImpExpController()->execute();
		}

		add_filter( $this->con()->prefix( 'delete_on_deactivate' ), function ( $isDelete ) {
			return $isDelete || $this->getOptions()->isOpt( 'delete_on_deactivate', 'Y' );
		} );

		$mod->getReportingController()->execute();
	}

	public function runDailyCron() {
		$this->con()->fireEvent( 'test_cron_run' );
		( new CleanStorage() )->run();
		( new Lib\PluginTelemetry() )->collectAndSend();
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( \class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', [ $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ], 0 );
		}
		if ( @\function_exists( '\wp_cache_setting' ) ) {
			@\wp_cache_setting( 'wp_super_cache_late_init', 1 );
		}
	}
}
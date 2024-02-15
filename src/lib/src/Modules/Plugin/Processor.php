<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		$mod = self::con()->getModule_Plugin();

		$this->removePluginConflicts();
		( new Lib\OverrideLocale() )->execute();

		new Events\StatsWriter();
		$mod->getShieldNetApiController()->execute();
		$mod->getPluginBadgeCon()->execute();
		( new Components\HttpHeadersCon() )->execute();

		( new Lib\AllowBetaUpgrades() )->execute();
		( new Lib\SiteHealthController() )->execute();

		if ( $this->opts()->isOpt( 'importexport_enable', 'Y' ) ) {
			$mod->getImpExpController()->execute();
		}

		add_filter( self::con()->prefix( 'delete_on_deactivate' ), function ( $isDelete ) {
			return $isDelete || $this->opts()->isOpt( 'delete_on_deactivate', 'Y' );
		} );

		$mod->getReportingController()->execute();
	}

	public function onWpInit() {
		( new Components\AnonRestApiDisable() )->execute();
	}

	public function runHourlyCron() {
		$this->setEarlyLoadOrder();
	}

	protected function setEarlyLoadOrder() {
		$active = get_option( 'active_plugins' );
		$pos = \array_search( self::con()->base_file, $active );
		if ( $pos > 2 ) {
			unset( $active[ $pos ] );
			\array_unshift( $active, self::con()->base_file );
			update_option( 'active_plugins', \array_values( $active ) );
		}
	}

	public function runDailyCron() {
		self::con()->fireEvent( 'test_cron_run' );
		( new Lib\PluginTelemetry() )->collectAndSend();
		( new Events\ConsolidateAllEvents() )->run();
		( new Components\CleanRubbish() )->execute();
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
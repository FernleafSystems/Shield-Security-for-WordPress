<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	use ModConsumer;

	protected function run() {
		$con = self::con();
		$mod = $con->getModule_Plugin();

		$this->removePluginConflicts();

		$con->getModule_License()->getLicenseHandler()->execute();

		if ( !$this->opts()->isPluginGloballyDisabled() && !$con->this_req->is_force_off ) {
			( new Components\IPsCon() )->execute();
			$con->getModule_HackGuard()->getScansCon()->execute();
			$con->getModule_Traffic()->getRequestLogger()->execute();
			$con->getModule_AuditTrail()->getAuditCon()->execute();
			( new Components\HttpHeadersCon() )->execute();
			$mod->getReportingController()->execute();
			new Events\StatsWriter();
			$mod->getPluginBadgeCon()->execute();
			( new Lib\AllowBetaUpgrades() )->execute();
			( new Components\AutoUpdatesCon() )->execute();
			$mod->getImpExpController()->execute();
			( new Lib\OverrideLocale() )->execute();
			( new Lib\SiteHealthController() )->execute();

			$con->getModule_Integrations()->getController_SpamForms()->execute();
			add_action( 'init', function () {
				self::con()->getModule_Integrations()->getController_UserForms()->execute();
			}, HookTimings::INIT_USER_FORMS_SETUP );
		}

		$con->getModule_Integrations()->getControllerMWP()->execute();
		$mod->getShieldNetApiController()->execute();

		add_filter( self::con()->prefix( 'delete_on_deactivate' ), function ( $isDelete ) {
			return $isDelete || $this->opts()->isOpt( 'delete_on_deactivate', 'Y' );
		} );
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
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	use ModConsumer;

	protected function run() {
		$con = self::con();
		$components = $con->comps;

		$this->removePluginConflicts();

		$components->license->execute();

		if ( !$components->opts_lookup->isPluginGloballyDisabled() && !$con->this_req->is_force_off ) {
			$components->ips_con->execute();
			$components->sec_admin->execute();
			$components->whitelabel->execute();
			$components->requests_log->execute();
			$components->activity_log->execute();
			$components->scans->execute();
			$components->file_locker->execute();
			$components->http_headers->execute();
			$components->reports->execute();
			$components->autoupdates->execute();
			$components->badge->execute();
			$components->import_export->execute();
			$components->comment_spam->execute();
			new Events\StatsWriter();
			( new Lib\AllowBetaUpgrades() )->execute();
			( new Lib\OverrideLocale() )->execute();

			$components->forms_spam->execute();
			add_action( 'init', function () {
				self::con()->comps->forms_users->execute();
			}, HookTimings::INIT_USER_FORMS_SETUP );
		}

		$components->mainwp->execute();
		$components->shieldnet->execute();

		add_filter( self::con()->prefix( 'delete_on_deactivate' ), function ( $isDelete ) {
			return $isDelete || self::con()->opts->optIs( 'delete_on_deactivate', 'Y' );
		} );
	}

	public function onWpInit() {
		( new Components\AnonRestApiDisable() )->execute();
		( new Lib\SiteHealthController() )->execute();
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
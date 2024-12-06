<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\Rename\RenameLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password\UserPasswordHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration\EmailValidate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\UserSessionHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Users\BulkUpdateUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Processor {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public function __construct() {
		$this->setupCronHooks();
	}

	protected function run() {
		$con = self::con();
		$components = $con->comps;

		$this->removePluginConflicts();

		$components->license->execute();

		if ( $components->opts_lookup->isPluginEnabled() ) {

			if ( !$con->this_req->is_force_off ) {
				$components->requests_log->execute();
				$components->activity_log->execute();
				$components->instant_alerts->execute();
				$components->sec_admin->execute();
				$components->ips_con->execute();
				$components->scans->execute();
				$components->file_locker->execute();
				$components->http_headers->execute();
				$components->reports->execute();
				$components->autoupdates->execute();
				$components->badge->execute();
				$components->import_export->execute();
				$components->comment_spam->execute();
				$components->whitelabel->execute();
				$components->integrations->execute();

				new Events\StatsWriter();
				( new Lib\AllowBetaUpgrades() )->execute();

				$components->forms_spam->execute();

				add_action( 'init', fn() => self::con()->comps->forms_users->execute(), HookTimings::INIT_USER_FORMS_SETUP );
				add_action( 'init', fn() => self::con()->comps->scans_queue->execute(), HookTimings::INIT_PROCESSOR_DEFAULT );

				( new RenameLogin() )->execute();

				( new Components\AnonRestApiDisable() )->execute();
				( new Lib\SiteHealthController() )->execute();

				// Adds last login indicator column
				add_filter( 'manage_users_columns', [ $this, 'addUserStatusLastLogin' ] );
				add_filter( 'wpmu_users_columns', [ $this, 'addUserStatusLastLogin' ] );

				// This controller handles visitor whitelisted status internally.
				self::con()->comps->user_suspend->execute();

				// All newly created users have their first seen and password start date set
				add_action( 'user_register',
					fn( $userID ) => self::con()->user_metas->for( Services::WpUsers()->getUserById( $userID ) ) );

				( new UserPasswordHandler() )->execute();
				( new EmailValidate() )->execute();
				( new UserSessionHandler() )->execute();
			}

			$components->mfa->execute();
		}

		$components->mainwp->execute();
		$components->shieldnet->execute();
		$components->wpcli->execute();

		add_filter( self::con()->prefix( 'delete_on_deactivate' ),
			fn( $isDelete ) => $isDelete || self::con()->opts->optIs( 'delete_on_deactivate', 'Y' ) );
	}

	public function runHourlyCron() {
		$this->setEarlyLoadOrder();
		( new BulkUpdateUserMeta() )->execute();
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
		self::con()->comps->mu->run();
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

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array|mixed $cols
	 * @return array|mixed
	 */
	public function addUserStatusLastLogin( $cols ) {

		if ( \is_array( $cols ) ) {
			$customColName = self::con()->prefix( 'col_user_status' );
			if ( !isset( $cols[ $customColName ] ) ) {
				$cols[ $customColName ] = __( 'User Status', 'wp-simple-firewall' );
			}

			add_filter( 'manage_users_custom_column', function ( $content, $colName, $userID ) use ( $customColName ) {

				if ( $colName === $customColName ) {
					$user = Services::WpUsers()->getUserById( $userID );
					if ( $user instanceof \WP_User ) {
						$con = self::con();

						$meta = $con->user_metas->for( $user );
						$lastLoginAt = $meta->record->last_login_at;
						$carbon = Services::Request()
										  ->carbon()
										  ->setTimestamp( $lastLoginAt );

						/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops\Record $ip */
						$ip = $con->db_con->ips->getQuerySelector()->byId( $meta->record->ip_ref );

						$content = \implode( '<br/>', \array_filter( \array_map(
							/**
							 * need to cast as string as crappy programmers can't use filters properly.
							 * @see https://wordpress.org/support/topic/firewall-creating-errors-on-site/
							 */
							fn( $line ) => \trim( (string)$line ),
							apply_filters( 'shield/user_status_column', [
								$content,
								sprintf( '<em title="%s">%s</em>: %s',
									$lastLoginAt > 0 ? $carbon->toIso8601String() : __( 'Not Recorded', 'wp-simple-firewall' ),
									__( 'Last Login', 'wp-simple-firewall' ),
									$lastLoginAt > 0 ? $carbon->diffForHumans() : __( 'Not Recorded', 'wp-simple-firewall' )
								),
								sprintf( '<em title="%s">%s</em>: %s',
									empty( $ip->ip ) ? __( 'Unknown', 'wp-simple-firewall' ) : $ip->ip, __( 'Last Known IP', 'wp-simple-firewall' ),
									empty( $ip->ip ) ? __( 'Unknown', 'wp-simple-firewall' ) : sprintf( '<a href="%s" target="_blank">%s</a>', $con->plugin_urls->ipAnalysis( $ip->ip ), $ip->ip )
								),
							], $user )
						) ) );
					}
				}

				return $content;
			}, 20, 3 );
		}

		return $cols;
	}
}
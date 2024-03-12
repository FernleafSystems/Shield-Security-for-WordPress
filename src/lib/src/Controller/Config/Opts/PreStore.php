<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\VerifySecurityAdminList;
use FernleafSystems\Wordpress\Services\Services;

class PreStore {

	use PluginControllerConsumer;

	public function run() {
		$this->audit();
		$this->comments();
		$this->headers();
		$this->ips();
		$this->lockdown();
		$this->loginGuard();
		$this->plugin();
		$this->scanners();
		$this->securityAdmin();
		$this->traffic();
		$this->user();
	}

	private function audit() {
		$opts = self::con()->opts;
		foreach ( [ 'log_level_db', 'log_level_file' ] as $optKey ) {
			$current = $opts->optGet( $optKey );
			if ( empty( $current ) ) {
				$opts->optReset( $optKey );
			}
			elseif ( \in_array( 'disabled', $opts->optGet( $optKey ) ) ) {
				$opts->optSet( $optKey, [ 'disabled' ] );
			}
		}
		if ( \in_array( 'same_as_db', $opts->optGet( 'log_level_file' ) ) ) {
			$opts->optSet( 'log_level_file', [ 'same_as_db' ] );
		}
	}

	private function comments() {
		$opts = self::con()->opts;
		if ( $opts->optChanged( 'trusted_user_roles' ) ) {
			$opts->optSet( 'trusted_user_roles',
				\array_unique( \array_filter( \array_map(
					function ( $role ) {
						return sanitize_key( \strtolower( $role ) );
					},
					$opts->optGet( 'trusted_user_roles' )
				) ) )
			);
		}
	}

	public function lockdown() :void {
		$opts = self::con()->opts;

		$exc = $opts->optGet( 'api_namespace_exclusions' );
		if ( !\in_array( 'shield', $exc ) ) {
			$exc[] = 'shield';
			$opts->optSet( 'api_namespace_exclusions', $exc );
		}
	}

	public function login() :void {
		$opts = self::con()->opts;

		if ( $opts->optIs( 'enable_antibot_check', 'Y' ) ) {
			$opts->optSet( 'enable_login_gasp_check', 'N' );
		}

		$opts->optSet( 'two_factor_auth_user_roles', self::con()->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() );

		$redirect = \preg_replace( '#[^\da-z_\-/.]#i', '', (string)$opts->optGet( 'rename_wplogin_redirect' ) );
		if ( !empty( $redirect ) ) {
			$redirect = \preg_replace( '#^http(s)?//.*/#iU', '', $redirect );
			if ( !empty( $redirect ) ) {
				$redirect = '/'.\ltrim( $redirect, '/' );
			}
		}
		$opts->optSet( 'rename_wplogin_redirect', $redirect );

		if ( empty( $opts->optGet( 'mfa_user_setup_pages' ) ) ) {
			$opts->optSet( 'mfa_user_setup_pages', [ 'profile' ] );
		}

		if ( $opts->optChanged( 'antibot_form_ids' ) ) {
			$opts->optSet( 'antibot_form_ids',
				\array_values( \array_unique( \array_filter(
					$opts->optGet( 'antibot_form_ids' ),
					function ( $id ) {
						return \trim( strip_tags( (string)$id ) );
					}
				) ) )
			);
		}

		if ( $opts->optChanged( 'rename_wplogin_path' ) ) {
			$path = $opts->optGet( 'rename_wplogin_path' );
			if ( !empty( $path ) ) {
				$path = \preg_replace( '#[^\da-zA-Z-]#', '', \trim( $path, '/' ) );
				$opts->optSet( 'rename_wplogin_path', $path );
			}
		}
	}

	public function ips() :void {
		$opts = self::con()->opts;

		if ( !\defined( \strtoupper( $opts->optGet( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$opts->optReset( 'auto_expire' );
		}

		if ( $opts->optChanged( 'request_whitelist' ) ) {
			$WP = Services::WpGeneral();
			$opts->optSet( 'request_whitelist',
				( new WildCardOptions() )->clean(
					$opts->optGet( 'request_whitelist' ),
					\array_unique( \array_map(
						function ( $url ) {
							return (string)wp_parse_url( $url, \PHP_URL_PATH );
						},
						[
							'/',
							$WP->getHomeUrl(),
							$WP->getWpUrl(),
							$WP->getAdminUrl( 'admin.php' ),
						]
					) ),
					WildCardOptions::URL_PATH
				)
			);
		}

		$dbhIPRules = self::con()->db_con->dbhIPRules();
		if ( $opts->optChanged( 'cs_block' ) && $opts->optIs( 'cs_block', 'disabled' ) ) {
			/** @var Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_CROWDSEC )->query();
		}
		if ( $opts->optChanged( 'transgression_limit' ) && $opts->optGet( 'transgression_limit' ) === 0 ) {
			/** @var Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_AUTO_BLOCK )->query();
		}
	}

	private function headers() {
		$opts = self::con()->opts;

		if ( $opts->optChanged( 'xcsp_custom' ) ) {
			$opts->optSet( 'xcsp_custom', \array_unique( \array_filter( \array_map(
				function ( $rule ) {
					$rule = \trim( \preg_replace( '#;|\s{2,}#', '', \html_entity_decode( $rule, \ENT_QUOTES ) ) );
					if ( !empty( $rule ) ) {
						$rule .= ';';
					}
					return $rule;
				},
				$opts->optGet( 'xcsp_custom' )
			) ) ) );
		}

		if ( empty( $opts->optGet( 'xcsp_custom' ) ) ) {
			$opts->optSet( 'enable_x_content_security_policy', 'N' );
		}
	}

	public function loginGuard() :void {
		if ( self::con()->opts->optChanged( 'enable_email_authentication' ) ) {
			try {
				self::con()->action_router->action( MfaEmailSendVerification::class );
			}
			catch ( \Exception $e ) {
			}
		}
	}

	public function plugin() :void {
		$opts = self::con()->opts;

		if ( $opts->optGet( 'ipdetect_at' ) === 0
			 || ( $opts->optChanged( 'visitor_address_source' ) && $opts->optGet( 'visitor_address_source' ) === 'AUTO_DETECT_IP' )
		) {
			$opts->optSet( 'ipdetect_at', 1 );
		}

		if ( !\preg_match( '#^[a-z]{2,3}(_[A-Z]{2,3})?$#', $opts->optGet( 'locale_override' ) ) ) {
			$opts->optSet( 'locale_override', '' );
		}

		if ( self::con()->comps->opts_lookup->enabledTelemetry() && $opts->optGet( 'tracking_permission_set_at' ) === 0 ) {
			$opts->optSet( 'tracking_permission_set_at', Services::Request()->ts() );
		}

		$tmp = $opts->optGet( 'preferred_temp_dir' );
		if ( !empty( $tmp ) && !Services::WpFs()->isAccessibleDir( $tmp ) ) {
			$opts->optSet( 'preferred_temp_dir', '' );
		}

		if ( $opts->optChanged( 'importexport_whitelist' ) ) {
			$opts->optSet( 'importexport_whitelist', \array_unique( \array_filter( \array_map(
				function ( $url ) {
					return Services::Data()->validateSimpleHttpUrl( $url );
				},
				$opts->optGet( 'importexport_whitelist' )
			) ) ) );
		}

		$url = Services::Data()->validateSimpleHttpUrl( $opts->optGet( 'importexport_masterurl' ) );
		$opts->optSet( 'importexport_masterurl', $url === false ? '' : $url );
	}

	private function securityAdmin() :void {
		$opts = self::con()->opts;

		// Restricting Activate Plugins also means restricting the rest.
		$p = $opts->optGet( 'admin_access_restrict_plugins' );
		if ( $opts->optChanged( 'admin_access_restrict_plugins' ) && \in_array( 'activate_plugins', $p ) ) {
			$opts->optSet( 'admin_access_restrict_plugins',
				\array_unique( \array_merge( $p, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$t = $opts->optGet( 'admin_access_restrict_themes' );
		if ( $opts->optChanged( 'admin_access_restrict_themes' )
			 && \in_array( 'switch_themes', $t ) && \in_array( 'edit_theme_options', $t ) ) {
			$opts->optSet( 'admin_access_restrict_themes',
				\array_unique( \array_merge( $t, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$posts = $opts->optGet( 'admin_access_restrict_posts' );
		if ( $opts->optChanged( 'admin_access_restrict_posts' ) && \in_array( 'edit', $posts ) ) {
			$opts->optSet( 'admin_access_restrict_posts',
				\array_unique( \array_merge( $posts, [ 'publish', 'delete' ] ) )
			);
		}

		if ( $opts->optChanged( 'sec_admin_users' ) ) {
			$opts->optSet( 'sec_admin_users',
				( new VerifySecurityAdminList() )->run( $opts->optGet( 'sec_admin_users' ) )
			);
		}

		self::con()->comps->whitelabel->verifyUrls();
		if ( $opts->optChanged( 'enable_mu' ) ) {
			self::con()->modules[ EnumModules::SECURITY_ADMIN ]->runMuHandler();
		}
	}

	private function scanners() {
		$con = self::con();
		$opts = $con->opts;

		if ( $opts->optChanged( 'scan_frequency' ) ) {
			$con->comps->scans->deleteCron();
		}

		if ( $opts->optChanged( 'file_locker' ) ) {
			$lockFiles = $opts->optGet( 'file_locker' );
			if ( !empty( $lockFiles ) ) {
				if ( \in_array( 'root_webconfig', $lockFiles ) && !Services::Data()->isWindows() ) {
					unset( $lockFiles[ \array_search( 'root_webconfig', $lockFiles ) ] );
					$opts->optSet( 'file_locker', $lockFiles );
				}
			}
			$opts->optSet( 'file_locker', $lockFiles );

			if ( \count( $lockFiles ) === 0 || !$con->comps->shieldnet->canHandshake()
			) {
				$con->comps->file_locker->purge();
			}
		}

		foreach ( $con->comps->scans->getAllScanCons() as $scanCon ) {
			if ( !$scanCon->isEnabled() ) {
				$scanCon->purge();
			}
		}

		if ( $opts->optChanged( 'scan_path_exclusions' ) ) {
			$opts->optSet( 'scan_path_exclusions',
				( new WildCardOptions() )->clean(
					$opts->optGet( 'scan_path_exclusions' ),
					\array_map( 'trailingslashit', [
						ABSPATH,
						path_join( ABSPATH, 'wp-admin' ),
						path_join( ABSPATH, 'wp-includes' ),
						untrailingslashit( WP_CONTENT_DIR ),
						path_join( WP_CONTENT_DIR, 'plugins' ),
						path_join( WP_CONTENT_DIR, 'themes' ),
					] ),
					WildCardOptions::FILE_PATH_REL
				)
			);
		}

		( new CleanLockRecords() )->run();
	}

	private function traffic() {
		$opts = self::con()->opts;

		if ( $opts->optChanged( 'custom_exclusions' ) ) {
			$opts->optSet( 'custom_exclusions', \array_filter( \array_map(
				function ( $excl ) {
					return \trim( esc_js( $excl ) );
				},
				$opts->optGet( 'custom_exclusions' )
			) ) );
		}

		if ( self::con()->comps->opts_lookup->isModFromOptEnabled( 'enable_limiter' ) ) {
			if ( $opts->optIs( 'enable_limiter', 'Y' ) && !$opts->optIs( 'enable_logger', 'Y' ) ) {
				$opts->optSet( 'enable_logger', 'Y' );
			}
			if ( $opts->optIs( 'enable_live_log', 'Y' ) && !$opts->optIs( 'enable_logger', 'Y' ) ) {
				$opts->optSet( 'enable_live_log', 'N' )
					 ->optSet( 'live_log_started_at', 0 );
			}
		}
	}

	public function user() :void {
		$opts = self::con()->opts;
		$optsLookup = self::con()->comps->opts_lookup;

		if ( $optsLookup->getSessionIdleInterval() > $optsLookup->getSessionMax() ) {
			$opts->optSet( 'session_idle_timeout_interval', $opts->optGet( 'session_timeout_interval' )*24 );
		}

		if ( $opts->optChanged( 'auto_idle_roles' ) ) {
			$opts->optSet( 'auto_idle_roles',
				\array_unique( \array_filter( \array_map(
					function ( $role ) {
						return \preg_replace( '#[^\s\da-z_-]#i', '', \trim( \strtolower( $role ) ) );
					},
					$opts->optGet( 'auto_idle_roles' )
				) ) )
			);
		}

		if ( $opts->optChanged( 'email_checks' ) ) {
			$opts->optSet( 'email_checks', \array_unique( \array_merge( $opts->optGet( 'email_checks' ), [ 'syntax' ] ) ) );
		}
	}
}
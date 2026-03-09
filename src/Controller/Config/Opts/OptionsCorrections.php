<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\VerifySecurityAdminList;
use FernleafSystems\Wordpress\Services\Services;

class OptionsCorrections {

	use PluginControllerConsumer;

	public function run() :void {
		$this->audit();
		$this->comments();
		$this->firewall();
		$this->headers();
		$this->ips();
		$this->login();
		$this->plugin();
		$this->scanners();
		$this->securityAdmin();
		$this->user();
	}

	/**
	 * @deprecated 21.3
	 */
	protected function removeDeprecated() {
	}

	/**
	 * @deprecated 21.3
	 */
	protected function removeModuleEnablers() {
	}

	private function audit() :void {
		$opts = self::con()->opts;

		if ( $opts->optIs( 'enable_limiter', 'Y' ) && !$opts->optIs( 'enable_logger', 'Y' ) ) {
			$opts->optSet( 'enable_logger', 'Y' );
		}
		if ( $opts->optIs( 'enable_live_log', 'Y' ) && !$opts->optIs( 'enable_logger', 'Y' ) ) {
			$opts->optSet( 'enable_live_log', 'N' )
					 ->optSet( 'live_log_started_at', 0 );
		}
	}

	private function comments() :void {
		$opts = self::con()->opts;
		if ( $opts->optChanged( 'trusted_user_roles' ) ) {
			$opts->optSet( 'trusted_user_roles',
				\array_unique( \array_filter( \array_map(
					fn( $role ) => sanitize_key( \strtolower( $role ) ),
					$opts->optGet( 'trusted_user_roles' )
				) ) )
			);
		}
	}

	private function firewall() :void {
		$opts = self::con()->opts;

		if ( $opts->optChanged( 'page_params_whitelist' ) ) {
			$parsed = [];
			foreach ( $opts->optGet( 'page_params_whitelist' ) as $line ) {
				$line = \str_replace( ' ', '', \trim( (string)$line ) );
				if ( !empty( $line ) ) {
					$parts = \explode( ',', $line );
					if ( \count( $parts ) > 1 ) {
						$page = \array_shift( $parts );
						if ( !isset( $parsed[ $page ] ) ) {
							$parsed[ $page ] = [];
						}
						$parsed[ $page ] = \array_map( '\\strtolower', \array_unique( \array_merge( $parsed[ $page ], $parts ) ) );
					}
				}
			}
			$final = [];
			foreach ( $parsed as $page => $params ) {
				\array_unshift( $params, $page );
				$final[] = \implode( ',', $params );
			}
			$opts->optSet( 'page_params_whitelist', $final );
		}

		$exclusions = $opts->optGet( 'api_namespace_exclusions' );
		if ( !\in_array( 'shield', $exclusions, true ) ) {
			$exclusions[] = 'shield';
			$opts->optSet( 'api_namespace_exclusions', $exclusions );
		}
	}

	private function headers() :void {
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

	private function ips() :void {
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
						fn( $url ) => (string)wp_parse_url( $url, \PHP_URL_PATH ),
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
	}

	private function login() :void {
		$opts = self::con()->opts;

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

		if ( $opts->optChanged( 'rename_wplogin_path' ) ) {
			$path = $opts->optGet( 'rename_wplogin_path' );
			if ( !empty( $path ) ) {
				$path = \preg_replace( '#[^\da-zA-Z-]#', '', \trim( $path, '/' ) );
				$opts->optSet( 'rename_wplogin_path', $path );
			}
		}
	}

	private function plugin() :void {
		$opts = self::con()->opts;

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

	private function scanners() :void {
		$opts = self::con()->opts;

		if ( $opts->optChanged( 'file_locker' ) ) {
			$lockFiles = $opts->optGet( 'file_locker' );
			if ( !empty( $lockFiles ) && \in_array( 'root_webconfig', $lockFiles, true ) && !Services::Data()->isWindows() ) {
				unset( $lockFiles[ \array_search( 'root_webconfig', $lockFiles, true ) ] );
			}
			$opts->optSet( 'file_locker', $lockFiles );
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
	}

	private function securityAdmin() :void {
		$opts = self::con()->opts;

		$p = $opts->optGet( 'admin_access_restrict_plugins' );
		if ( $opts->optChanged( 'admin_access_restrict_plugins' ) && \in_array( 'activate_plugins', $p, true ) ) {
			$opts->optSet( 'admin_access_restrict_plugins',
				\array_unique( \array_merge( $p, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		$t = $opts->optGet( 'admin_access_restrict_themes' );
		if ( $opts->optChanged( 'admin_access_restrict_themes' )
			 && \in_array( 'switch_themes', $t, true ) && \in_array( 'edit_theme_options', $t, true ) ) {
			$opts->optSet( 'admin_access_restrict_themes',
				\array_unique( \array_merge( $t, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$posts = $opts->optGet( 'admin_access_restrict_posts' );
		if ( $opts->optChanged( 'admin_access_restrict_posts' ) && \in_array( 'edit', $posts, true ) ) {
			$opts->optSet( 'admin_access_restrict_posts',
				\array_unique( \array_merge( $posts, [ 'publish', 'delete' ] ) )
			);
		}

		if ( $opts->optChanged( 'sec_admin_users' ) ) {
			$opts->optSet( 'sec_admin_users',
				( new VerifySecurityAdminList() )->run( $opts->optGet( 'sec_admin_users' ) )
			);
		}
	}

	private function user() :void {
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

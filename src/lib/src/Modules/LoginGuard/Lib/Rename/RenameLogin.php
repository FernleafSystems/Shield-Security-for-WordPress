<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\Rename;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RenameLogin {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions
			   && !self::con()->this_req->wp_is_xmlrpc
			   && $this->isEnabled();
	}

	public function isEnabled() :bool {
		return !empty( $this->customPath() )
			   && !$this->hasPluginConflict()
			   && !$this->hasUnsupportedConfiguration();
	}

	public function customPath() :string {
		return self::con()->opts->optGet( 'rename_wplogin_path' );
	}

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_LOGIN_RENAME );
	}

	public function onWpInit() {
		if ( Services::WpGeneral()->isLoginUrl() && Services::WpUsers()->isUserLoggedIn() ) {
			return;
		}
		if ( is_admin() && !Services::WpUsers()->isUserLoggedIn() ) {
			return;
		}
		$this->replaceLoginURL();

		// Intercept requests
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 11 );
	}

	public function onWpLoaded() {

		$this->doBlockPossibleWpLoginLoad();
		$this->loadWpLoginContent(); // Loads the wp-login.php if the correct URL is loaded

		// Shouldn't be necessary, but in-case something else includes the wp-login.php, we block that too.
		add_action( 'login_init', [ $this, 'aLoginFormAction' ], 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			add_filter( 'wp_redirect', [ $this, 'fProtectUnauthorizedLoginRedirect' ], 50 );
		}
		add_filter( 'register_url', [ $this, 'blockRegisterUrlRedirect' ], 20 );

		add_filter( 'et_anticipate_exceptions', [ $this, 'fAddToEtMaintenanceExceptions' ] );
	}

	private function replaceLoginURL() {
		add_filter( 'site_url', [ $this, 'fCheckForLoginPhp' ], 20 );
		add_filter( 'network_site_url', [ $this, 'fCheckForLoginPhp' ], 20 );
		add_filter( 'wp_redirect', [ $this, 'fCheckForLoginPhp' ], 20 );
	}

	private function hasPluginConflict() :bool {
		$msg = '';
		$isConflicted = false;

		$path = $this->customPath();

		$WP = Services::WpGeneral();
		if ( $WP->isMultisite() ) {
			$msg = __( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.', 'wp-simple-firewall' );
			$isConflicted = true;
		}
		elseif ( \class_exists( 'Rename_WP_Login' ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Rename WP Login' );
			$isConflicted = true;
		}
		elseif ( \class_exists( 'Theme_My_Login' ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Theme My Login' );
			$isConflicted = true;
		}
		elseif ( !$WP->isPermalinksEnabled() ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have not enabled %s.', 'wp-simple-firewall' ), __( 'Permalinks' ) );
			$isConflicted = true;
		}
		elseif ( $WP->isPermalinksEnabled() && ( $WP->getDoesWpSlugExist( $path ) || \in_array( $path, $WP->getAutoRedirectLocations() ) ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is already used on your WordPress site.', 'wp-simple-firewall' ), $path );
			$isConflicted = true;
		}

		if ( $isConflicted ) {
			self::con()->admin_notices->addFlash(
				sprintf( '<strong>%s</strong>: %s', __( 'Warning', 'wp-simple-firewall' ), $msg ),
				null,
				true
			);
		}

		return $isConflicted;
	}

	private function hasUnsupportedConfiguration() :bool {
		$unsupported = empty( Services::Request()->getPath() );
		if ( $unsupported ) {
			self::con()->admin_notices->addFlash(
				sprintf(
					'<strong>%s</strong>: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Your login URL is unchanged because your current hosting/PHP configuration cannot parse the necessary information.', 'wp-simple-firewall' )
				),
				null,
				true
			);
		}
		return $unsupported;
	}

	public function doBlockPossibleWpLoginLoad() {

		// To begin, we block if it's a request to the admin area and the user isn't logged in (and it's not ajax)
		$doBlock = is_admin() && !Services::WpGeneral()->isAjax() && !Services::WpUsers()->isUserLoggedIn();

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$doBlock ) {
			$path = \trim( Services::Request()->getPath(), '/' );
			$possible = [
				\trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				\trim( home_url( 'wp-signup.php', 'relative' ), '/' ),
				\trim( site_url( 'wp-signup.php', 'relative' ), '/' ),
				// \trim( site_url( 'wp-login.php', 'relative' ), '/' ), our own filters in run() scuttle us here so we have to build it manually
				\trim( \rtrim( site_url( '', 'relative' ), '/' ).'/wp-login.php', '/' ),
				\trim( home_url( 'login', 'relative' ), '/' ),
				\trim( site_url( 'login', 'relative' ), '/' )
			];
			$doBlock = !empty( $path )
					   && ( \in_array( $path, $possible ) || \preg_match( '/wp-login\.php/i', $path ) );
		}

		if ( $doBlock ) {
			$this->doWpLoginFailedRedirect404();
		}
	}

	/**
	 * @param string $location
	 * @return string
	 */
	public function fCheckForLoginPhp( $location ) {

		$redirectPath = wp_parse_url( $location, \PHP_URL_PATH );
		if ( !empty( $redirectPath ) && \str_contains( $redirectPath, 'wp-login.php' ) ) {

			$queryArgs = \explode( '?', $location );
			$location = home_url( $this->customPath() );
			if ( !empty( $queryArgs[ 1 ] ) ) {
				$location .= '?'.$queryArgs[ 1 ];
			}
		}

		return $location;
	}

	/**
	 * @param string $location
	 * @return string
	 */
	public function fProtectUnauthorizedLoginRedirect( $location ) {
		if ( !Services::WpGeneral()->isLoginUrl() && !Services::WpUsers()->isUserLoggedIn() ) {
			if ( \trim( (string)\wp_parse_url( $location, \PHP_URL_PATH ), '/' ) === $this->customPath() ) {
				$this->doWpLoginFailedRedirect404();
			}
		}
		return $location;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function blockRegisterUrlRedirect( $url ) {
		if ( \strpos( Services::Request()->getPath(), 'wp-register.php' ) ) {
			$this->doWpLoginFailedRedirect404();
		}
		return $url;
	}

	public function loadWpLoginContent() {
		if ( Services::WpGeneral()->isLoginUrl() ) {
			nocache_headers();
			Services::WpGeneral()->turnOffCache();
			// To prevent PHP warnings about undefined vars
			$user_login = $error = '';
			@require_once( ABSPATH.'wp-login.php' );
			die();
		}
	}

	public function aLoginFormAction() {
		if ( !Services::WpGeneral()->isLoginUrl() ) {
			$this->doWpLoginFailedRedirect404();
		}
	}

	/**
	 * Add the custom login URL to the Elegant Themes Maintenance Mode plugin URL exceptions list
	 * @param array $urlExceptions
	 * @return array
	 */
	public function fAddToEtMaintenanceExceptions( $urlExceptions ) {
		$urlExceptions[] = $this->customPath();
		return $urlExceptions;
	}

	/**
	 * Will by default send a 404 response screen. Has a filter to specify redirect URL.
	 */
	protected function doWpLoginFailedRedirect404() {

		self::con()->fireEvent( 'hide_login_url' );

		$redirectPath = self::con()->opts->optGet( 'rename_wplogin_redirect' );
		$redirectUrl = empty( $redirectPath ) ? '' : site_url( $redirectPath );
		$redirectUrl = apply_filters( 'shield/renamewplogin_redirect_url',
			apply_filters( 'icwp_shield_renamewplogin_redirect_url', $redirectUrl ) );

		if ( !empty( $redirectUrl ) ) {
			$redirectUrl = esc_url( $redirectUrl );
			if ( @wp_parse_url( $redirectUrl ) !== false ) {
				Services::Response()->redirect( $redirectUrl, [], false );
			}
		}

		Services::Response()->sendApache404( '', Services::WpGeneral()->getHomeUrl() );
		die();
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\Rename;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

class RenameLogin {

	use Modules\ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !Services::IP()->isLoopback()
			   && !empty( $opts->getCustomLoginPath() )
			   && !$mod->isVisitorWhitelisted()
			   && !$this->hasPluginConflict() && !$this->hasUnsupportedConfiguration();
	}

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ], 9 );
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
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$msg = '';
		$isConflicted = false;

		$path = $opts->getCustomLoginPath();

		$WP = Services::WpGeneral();
		if ( $WP->isMultisite() ) {
			$msg = __( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.', 'wp-simple-firewall' );
			$isConflicted = true;
		}
		elseif ( class_exists( 'Rename_WP_Login' ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Rename WP Login' );
			$isConflicted = true;
		}
		elseif ( class_exists( 'Theme_My_Login' ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Theme My Login' );
			$isConflicted = true;
		}
		elseif ( !$WP->isPermalinksEnabled() ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have not enabled %s.', 'wp-simple-firewall' ), __( 'Permalinks' ) );
			$isConflicted = true;
		}
		elseif ( $WP->isPermalinksEnabled() && ( $WP->getDoesWpSlugExist( $path ) || in_array( $path, $WP->getAutoRedirectLocations() ) ) ) {
			$msg = sprintf( __( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is already used on your WordPress site.', 'wp-simple-firewall' ), $path );
			$isConflicted = true;
		}

		if ( $isConflicted ) {
			$mod->setFlashAdminNotice( sprintf( '<strong>%s</strong>: %s',
				__( 'Warning', 'wp-simple-firewall' ),
				$msg
			), null, true );
		}

		return $isConflicted;
	}

	private function hasUnsupportedConfiguration() :bool {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$path = Services::Request()->getPath();

		$unsupported = empty( $path );
		if ( $unsupported ) {
			$mod->setFlashAdminNotice(
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
		$doBlock = is_admin() && !Services::WpGeneral()->isAjax()
				   && !Services::WpUsers()->isUserLoggedIn();

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$doBlock ) {
			$path = trim( Services::Request()->getPath(), '/' );
			$possible = [
				trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				trim( home_url( 'wp-signup.php', 'relative' ), '/' ),
				trim( site_url( 'wp-signup.php', 'relative' ), '/' ),
				// trim( site_url( 'wp-login.php', 'relative' ), '/' ), our own filters in run() scuttle us here so we have to build it manually
				trim( rtrim( site_url( '', 'relative' ), '/' ).'/wp-login.php', '/' ),
				trim( home_url( 'login', 'relative' ), '/' ),
				trim( site_url( 'login', 'relative' ), '/' )
			];
			$doBlock = !empty( $path )
					   && ( in_array( $path, $possible ) || preg_match( '/wp-login\.php/i', $path ) );
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
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$redirectPath = parse_url( $location, PHP_URL_PATH );
		if ( strpos( $redirectPath, 'wp-login.php' ) !== false ) {

			$loginUrl = home_url( $opts->getCustomLoginPath() );
			$queryArgs = explode( '?', $location );
			if ( !empty( $queryArgs[ 1 ] ) ) {
				parse_str( $queryArgs[ 1 ], $newQueryArgs );
				$loginUrl = add_query_arg( $newQueryArgs, $loginUrl );
			}
			$location = $loginUrl;
		}

		return $location;
	}

	/**
	 * @param string $location
	 * @return string
	 */
	public function fProtectUnauthorizedLoginRedirect( $location ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpGeneral()->isLoginUrl() ) {
			$sRedirectPath = trim( parse_url( $location, PHP_URL_PATH ), '/' );
			$bRedirectIsHiddenUrl = ( $sRedirectPath == $opts->getCustomLoginPath() );
			if ( $bRedirectIsHiddenUrl && !Services::WpUsers()->isUserLoggedIn() ) {
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
		if ( strpos( Services::Request()->getPath(), 'wp-register.php' ) ) {
			$this->doWpLoginFailedRedirect404();
			die();
		}
		return $url;
	}

	public function loadWpLoginContent() {
		if ( Services::WpGeneral()->isLoginUrl() ) {
			// To prevent PHP warnings about undefined vars
			$user_login = $error = '';
			@require_once( ABSPATH.'wp-login.php' );
			die();
		}
	}

	public function aLoginFormAction() {
		if ( !Services::WpGeneral()->isLoginUrl() ) {
			$this->doWpLoginFailedRedirect404();
			die();
		}
	}

	/**
	 * Add the custom login URL to the Elegant Themes Maintenance Mode plugin URL exceptions list
	 * @param array $urlExceptions
	 * @return array
	 */
	public function fAddToEtMaintenanceExceptions( $urlExceptions ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$urlExceptions[] = $opts->getCustomLoginPath();
		return $urlExceptions;
	}

	/**
	 * Will by default send a 404 response screen. Has a filter to specify redirect URL.
	 */
	protected function doWpLoginFailedRedirect404() {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$this->getCon()->fireEvent( 'hide_login_url' );

		$redirectPath = $opts->getHiddenLoginRedirect();
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
	}
}
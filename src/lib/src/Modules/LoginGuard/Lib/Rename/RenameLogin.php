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
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !Services::IP()->isLoopback()
			   && !empty( $opts->getCustomLoginPath() )
			   && !$this->hasPluginConflict() && !$this->hasUnsupportedConfiguration();
	}

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ], 9 );
	}

	public function onWpInit() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();

		if ( Services::WpGeneral()->isLoginUrl() &&
			 ( $mod->isVisitorWhitelisted() || Services::WpUsers()->isUserLoggedIn() ) ) {
			return;
		}
		if ( is_admin() && $mod->isVisitorWhitelisted() && !Services::WpUsers()->isUserLoggedIn() ) {
			return;
		}

		$this->doBlockPossibleWpLoginLoad();

		// Loads the wp-login.php if the correct URL is loaded
		add_action( 'wp_loaded', [ $this, 'aLoadWpLogin' ] );

		// Shouldn't be necessary, but in-case something else includes the wp-login.php, we block that too.
		add_action( 'login_init', [ $this, 'aLoginFormAction' ], 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		add_filter( 'site_url', [ $this, 'fCheckForLoginPhp' ], 20, 1 );
		add_filter( 'network_site_url', [ $this, 'fCheckForLoginPhp' ], 20, 1 );
		add_filter( 'wp_redirect', [ $this, 'fCheckForLoginPhp' ], 20, 1 );
		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			add_filter( 'wp_redirect', [ $this, 'fProtectUnauthorizedLoginRedirect' ], 50, 1 );
		}
		add_filter( 'register_url', [ $this, 'blockRegisterUrlRedirect' ], 20, 1 );

		add_filter( 'et_anticipate_exceptions', [ $this, 'fAddToEtMaintenanceExceptions' ] );
	}

	private function hasPluginConflict() :bool {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$sMessage = '';
		$bConflicted = false;

		$path = $opts->getCustomLoginPath();

		$WP = Services::WpGeneral();
		if ( $WP->isMultisite() ) {
			$sMessage = __( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.', 'wp-simple-firewall' );
			$bConflicted = true;
		}
		elseif ( class_exists( 'Rename_WP_Login' ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Rename WP Login' );
			$bConflicted = true;
		}
		elseif ( class_exists( 'Theme_My_Login' ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Theme My Login' );
			$bConflicted = true;
		}
		elseif ( !$WP->isPermalinksEnabled() ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have not enabled %s.', 'wp-simple-firewall' ), __( 'Permalinks' ) );
			$bConflicted = true;
		}
		elseif ( $WP->isPermalinksEnabled() && ( $WP->getDoesWpSlugExist( $path ) || in_array( $path, $WP->getAutoRedirectLocations() ) ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is reserved on your WordPress site.', 'wp-simple-firewall' ), $path );
			$bConflicted = true;
		}

		if ( $bConflicted ) {
			$sNoticeMessage = sprintf( '<strong>%s</strong>: %s',
				__( 'Warning', 'wp-simple-firewall' ),
				$sMessage
			);
			$mod->setFlashAdminNotice( $sNoticeMessage, true );
		}

		return $bConflicted;
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
				true
			);
		}

		return $unsupported;
	}

	public function doBlockPossibleWpLoginLoad() {

		// To begin, we block if it's an access to the admin area and the user isn't logged in (and it's not ajax)
		$bDoBlock = is_admin() && !Services::WpGeneral()->isAjax()
					&& !Services::WpUsers()->isUserLoggedIn();

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$bDoBlock ) {
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
			$bDoBlock = !empty( $path )
						&& ( in_array( $path, $possible ) || preg_match( '/wp-login\.php/i', $path ) );
		}

		if ( $bDoBlock ) {
			$this->doWpLoginFailedRedirect404();
		}
	}

	/**
	 * @param string $sLocation
	 * @return string
	 */
	public function fCheckForLoginPhp( $sLocation ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$sRedirectPath = parse_url( $sLocation, PHP_URL_PATH );
		if ( strpos( $sRedirectPath, 'wp-login.php' ) !== false ) {

			$sLoginUrl = home_url( $opts->getCustomLoginPath() );
			$aQueryArgs = explode( '?', $sLocation );
			if ( !empty( $aQueryArgs[ 1 ] ) ) {
				parse_str( $aQueryArgs[ 1 ], $aNewQueryArgs );
				$sLoginUrl = add_query_arg( $aNewQueryArgs, $sLoginUrl );
			}
			return $sLoginUrl;
		}
		return $sLocation;
	}

	/**
	 * @param string $sLocation
	 * @return string
	 */
	public function fProtectUnauthorizedLoginRedirect( $sLocation ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpGeneral()->isLoginUrl() ) {
			$sRedirectPath = trim( parse_url( $sLocation, PHP_URL_PATH ), '/' );
			$bRedirectIsHiddenUrl = ( $sRedirectPath == $opts->getCustomLoginPath() );
			if ( $bRedirectIsHiddenUrl && !Services::WpUsers()->isUserLoggedIn() ) {
				$this->doWpLoginFailedRedirect404();
			}
		}
		return $sLocation;
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

	public function aLoadWpLogin() {
		if ( Services::WpGeneral()->isLoginUrl() ) {
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
	 * @param array $aUrlExceptions
	 * @return array
	 */
	public function fAddToEtMaintenanceExceptions( $aUrlExceptions ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$aUrlExceptions[] = $opts->getCustomLoginPath();
		return $aUrlExceptions;
	}

	/**
	 * Will by default send a 404 response screen. Has a filter to specify redirect URL.
	 */
	protected function doWpLoginFailedRedirect404() {
		$this->getCon()->fireEvent( 'hide_login_url' );

		$sRedirectUrl = apply_filters( 'icwp_shield_renamewplogin_redirect_url', false );
		if ( !empty( $sRedirectUrl ) ) {
			$sRedirectUrl = esc_url( $sRedirectUrl );
			if ( @parse_url( $sRedirectUrl ) !== false ) {
				Services::Response()->redirect( $sRedirectUrl, [], false );
			}
		}

		Services::Response()->sendApache404( '', Services::WpGeneral()->getHomeUrl() );
	}
}
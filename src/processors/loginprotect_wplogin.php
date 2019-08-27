<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect_WpLogin extends ICWP_WPSF_Processor_BaseWpsf {

	public function onWpInit() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		if ( $this->checkForPluginConflict() || $this->checkForUnsupportedConfiguration() ) {
			return;
		}
		if ( Services::WpGeneral()->isLoginUrl() &&
			 ( $oMod->isVisitorWhitelisted() || Services::WpUsers()->isUserLoggedIn() ) ) {
			return;
		}
		if ( is_admin() && $oMod->isVisitorWhitelisted() && !Services::WpUsers()->isUserLoggedIn() ) {
			return;
		}

		$this->doBlockPossibleWpLoginLoad();

		// Loads the wp-login.php if the correct URL is loaded
		add_action( 'wp_loaded', [ $this, 'aLoadWpLogin' ] );

		// Shouldn't be necessary, but in-case something else includes the wp-login.php, we block that too.
		add_action( 'login_init', [ $this, 'aLoginFormAction' ], 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		add_filter( 'site_url', [ $this, 'fCheckForLoginPhp' ], 20, 2 );
		add_filter( 'network_site_url', [ $this, 'fCheckForLoginPhp' ], 20, 2 );
		add_filter( 'wp_redirect', [ $this, 'fCheckForLoginPhp' ], 20, 2 );
		add_filter( 'wp_redirect', [ $this, 'fProtectUnauthorizedLoginRedirect' ], 50, 2 );
		add_filter( 'register_url', [ $this, 'blockRegisterUrlRedirect' ], 20, 1 );

		add_filter( 'et_anticipate_exceptions', [ $this, 'fAddToEtMaintenanceExceptions' ] );
	}

	/**
	 * @return bool - true if conflict exists
	 */
	protected function checkForPluginConflict() {

		$sMessage = '';
		$bConflicted = false;

		$sCustomLoginPath = $this->getLoginPath();

		$oWp = Services::WpGeneral();
		if ( $oWp->isMultisite() ) {
			$sMessage = __( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.', 'wp-simple-firewall' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Rename_WP_Login' ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Rename WP Login' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Theme_My_Login' ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.', 'wp-simple-firewall' ), 'Theme My Login' );
			$bConflicted = true;
		}
		else if ( !$oWp->isPermalinksEnabled() ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have not enabled %s.', 'wp-simple-firewall' ), __( 'Permalinks' ) );
			$bConflicted = true;
		}
		else if ( $oWp->isPermalinksEnabled() && ( $oWp->getDoesWpSlugExist( $sCustomLoginPath ) || in_array( $sCustomLoginPath, $oWp->getAutoRedirectLocations() ) ) ) {
			$sMessage = sprintf( __( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is reserved on your WordPress site.', 'wp-simple-firewall' ), $sCustomLoginPath );
			$bConflicted = true;
		}

		if ( $bConflicted ) {
			$sNoticeMessage = sprintf( '<strong>%s</strong>: %s',
				__( 'Warning', 'wp-simple-firewall' ),
				$sMessage
			);
			$this->loadWpNotices()->addRawAdminNotice( $sNoticeMessage, 'error' );
		}

		return $bConflicted;
	}

	/**
	 * @return bool
	 */
	protected function checkForUnsupportedConfiguration() {
		$sPath = Services::Request()->getPath();
		if ( empty( $sPath ) ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				__( 'Warning', 'wp-simple-firewall' ),
				__( 'Your login URL is unchanged because your current hosting/PHP configuration cannot parse the necessary information.', 'wp-simple-firewall' )
			);
			$this->loadWpNotices()->addRawAdminNotice( $sNoticeMessage, 'error' );
			return true;
		}
		return false;
	}

	/**
	 */
	public function doBlockPossibleWpLoginLoad() {

		// To begin, we block if it's an access to the admin area and the user isn't logged in (and it's not ajax)
		$bDoBlock = is_admin()
					&& !Services::WpGeneral()->isAjax() && !Services::WpUsers()->isUserLoggedIn();

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$bDoBlock ) {
			$sPath = trim( Services::Request()->getPath(), '/' );
			$aPossiblePaths = [
				trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				trim( home_url( 'wp-signup.php', 'relative' ), '/' ),
				trim( site_url( 'wp-signup.php', 'relative' ), '/' ),
				// trim( site_url( 'wp-login.php', 'relative' ), '/' ), our own filters in run() scuttle us here so we have to build it manually
				trim( rtrim( site_url( '', 'relative' ), '/' ).'/wp-login.php', '/' ),
				trim( home_url( 'login', 'relative' ), '/' ),
				trim( site_url( 'login', 'relative' ), '/' )
			];
			$bDoBlock = !empty( $sPath )
						&& ( in_array( $sPath, $aPossiblePaths ) || preg_match( '/wp-login\.php/i', $sPath ) );
		}

		if ( $bDoBlock ) {
			$this->doWpLoginFailedRedirect404();
		}
	}

	/**
	 * @return string
	 */
	protected function getLoginPath() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getCustomLoginPath();
	}

	/**
	 * @param string $sLocation
	 * @param string $mStatus
	 * @return string
	 */
	public function fCheckForLoginPhp( $sLocation, $mStatus ) {

		$sRedirectPath = parse_url( $sLocation, PHP_URL_PATH );
		if ( strpos( $sRedirectPath, 'wp-login.php' ) !== false ) {

			$sLoginUrl = home_url( $this->getLoginPath() );
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
	 * @param string $mStatus
	 * @return string
	 */
	public function fProtectUnauthorizedLoginRedirect( $sLocation, $mStatus ) {

		if ( !Services::WpGeneral()->isLoginUrl() ) {
			$sRedirectPath = trim( parse_url( $sLocation, PHP_URL_PATH ), '/' );
			$bRedirectIsHiddenUrl = ( $sRedirectPath == $this->getLoginPath() );
			if ( $bRedirectIsHiddenUrl && !Services::WpUsers()->isUserLoggedIn() ) {
				$this->doWpLoginFailedRedirect404();
			}
		}
		return $sLocation;
	}

	/**
	 * @param string $sUrl
	 * @return string
	 */
	public function blockRegisterUrlRedirect( $sUrl ) {
		$sPath = Services::Request()->getPath();
		if ( strpos( $sPath, 'wp-register.php' ) ) {
			$this->doWpLoginFailedRedirect404();
			die();
		}
		return $sUrl;
	}

	/**
	 */
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
		$aUrlExceptions[] = $this->getLoginPath();
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
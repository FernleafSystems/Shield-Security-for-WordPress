<?php

class ICWP_WPSF_Processor_LoginProtect_WpLogin extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( !$oFO->isCustomLoginPathEnabled() || $this->checkForPluginConflict() || $this->checkForUnsupportedConfiguration() ) {
			return;
		}

		// Loads the wp-login.php if the correct URL is loaded
		add_action( 'init', array( $this, 'doBlockPossibleWpLoginLoad' ) );

		// Loads the wp-login.php is the correct URL is loaded
		add_filter( 'wp_loaded', array( $this, 'aLoadWpLogin' ) );

		// Shouldn't be necessary, but in-case something else includes the wp-login.php, we block that too.
		add_action( 'login_init', array( $this, 'aLoginFormAction' ), 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		add_filter( 'site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'network_site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'wp_redirect', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'wp_redirect', array( $this, 'fProtectUnauthorizedLoginRedirect' ), 50, 2 );
		add_filter( 'register_url', array( $this, 'blockRegisterUrlRedirect' ), 20, 1 );

		add_filter( 'et_anticipate_exceptions', array( $this, 'fAddToEtMaintenanceExceptions' ) );
	}

	/**
	 * @return bool - true if conflict exists
	 */
	protected function checkForPluginConflict() {

		$sMessage = '';
		$bConflicted = false;

		$sCustomLoginPath = $this->getLoginPath();

		$oWp = $this->loadWp();
		if ( $oWp->isMultisite() ) {
			$sMessage = _wpsf__( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Rename_WP_Login' ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.' ), 'Rename WP Login' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Theme_My_Login' ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.' ), 'Theme My Login' );
			$bConflicted = true;
		}
		else if ( !$oWp->isPermalinksEnabled() ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have not enabled %s.' ), __( 'Permalinks' ) );
			$bConflicted = true;
		}
		else if ( $oWp->isPermalinksEnabled() && ( $oWp->getDoesWpSlugExist( $sCustomLoginPath ) || in_array( $sCustomLoginPath, $oWp->getAutoRedirectLocations() ) ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is reserved on your WordPress site.' ), $sCustomLoginPath );
			$bConflicted = true;
		}

		if ( $bConflicted ) {
			$sNoticeMessage = sprintf( '<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
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
		$aRequestParts = $this->loadRequest()->getUriParts();
		if ( $aRequestParts === false || empty( $aRequestParts[ 'path' ] ) ) {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				_wpsf__( 'Your login URL is unchanged because your current hosting/PHP configuration cannot parse the necessary information.' )
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
		$bDoBlock = ( is_admin() && !$this->loadWp()->isAjax() && !$this->loadWpUsers()->isUserLoggedIn() );

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$bDoBlock ) {
			$sPath = trim( $this->loadRequest()->getPath(), '/' );
			$aPossiblePaths = array(
				trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				trim( home_url( 'wp-signup.php', 'relative' ), '/' ),
				trim( site_url( 'wp-signup.php', 'relative' ), '/' ),
				// trim( site_url( 'wp-login.php', 'relative' ), '/' ), our own filters in run() scuttle us here so we have to build it manually
				trim( rtrim( site_url( '', 'relative' ), '/' ).'/wp-login.php', '/' ),
				trim( home_url( 'login', 'relative' ), '/' ),
				trim( site_url( 'login', 'relative' ), '/' )
			);
			$bDoBlock = !empty( $sPath )
						&& ( in_array( $sPath, $aPossiblePaths ) || preg_match( '/wp-login\.php/i', $sPath ) );
		}

		if ( $bDoBlock ) {
			// We now black mark this IP
//			add_filter( $this->getMod()->prefix( 'ip_black_mark' ), '__return_true' );
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

		if ( !$this->loadWp()->isRequestLoginUrl() ) {
			$sRedirectPath = trim( parse_url( $sLocation, PHP_URL_PATH ), '/' );
			$bRedirectIsHiddenUrl = ( $sRedirectPath == $this->getLoginPath() );
			if ( $bRedirectIsHiddenUrl && !$this->loadWpUsers()->isUserLoggedIn() ) {
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
		$aParts = $this->loadRequest()->getUriParts();
		if ( is_array( $aParts ) && !empty( $aParts[ 'path' ] ) && strpos( $aParts[ 'path' ], 'wp-register.php' ) ) {
			$this->doWpLoginFailedRedirect404();
			die();
		}
		return $sUrl;
	}

	/**
	 * @return string|void
	 */
	public function aLoadWpLogin() {
		if ( $this->loadWp()->isRequestLoginUrl() ) {
			@require_once( ABSPATH.'wp-login.php' );
			die();
		}
	}

	public function aLoginFormAction() {
		if ( !$this->loadWp()->isRequestLoginUrl() ) {
			// We now black mark this IP
//			add_filter( $this->getMod()->prefix( 'ip_black_mark' ), '__return_true' );
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

		$this->doStatIncrement( 'login.rename.fail' );

		$sRedirectUrl = apply_filters( 'icwp_shield_renamewplogin_redirect_url', false );
		if ( !empty( $sRedirectUrl ) ) {
			$sRedirectUrl = esc_url( $sRedirectUrl );
			if ( @parse_url( $sRedirectUrl ) !== false ) {
				$this->loadWp()->doRedirect( $sRedirectUrl, array(), false );
			}
		}

		$this->loadRequest()->sendResponseApache404( '', $this->loadWp()->getHomeUrl() );
	}
}
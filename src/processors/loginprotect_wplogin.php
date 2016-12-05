<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_WpLogin', false ) ):

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_LoginProtect_WpLogin extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( !$oFO->getIsCustomLoginPathEnabled() || $this->checkForPluginConflict() || $this->checkForUnsupportedConfiguration() ) {
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
		add_filter( 'register_url', array( $this, 'blockRegisterUrlRedirect' ), 20, 1 );

		add_filter( 'et_anticipate_exceptions', array( $this, 'fAddToEtMaintenanceExceptions' ) ) ;
	}

	/**
	 * @return bool - true if conflict exists
	 */
	protected function checkForPluginConflict() {

		$sMessage = '';
		$bConflicted = false;

		$sCustomLoginPath = $this->getLoginPath();

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $oWp->isMultisite() ) {
			$sMessage = _wpsf__( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Rename_WP_Login', false ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.' ), 'Rename WP Login' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Theme_My_Login', false ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have the "%s" plugin installed and it is active.' ), 'Theme My Login' );
			$bConflicted = true;
		}
		else if ( !$oWp->getIsPermalinksEnabled() ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have not enabled %s.' ), __( 'Permalinks' ) );
			$bConflicted = true;
		}
		else if ( $oWp->getIsPermalinksEnabled() && ( $oWp->getDoesWpSlugExist( $sCustomLoginPath ) || in_array( $sCustomLoginPath, $oWp->getAutoRedirectLocations() ) ) ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have chosen a path ("%s") that is reserved on your WordPress site.' ), $sCustomLoginPath );
			$bConflicted = true;
		}

		if ( $bConflicted ) {
			$sNoticeMessage = sprintf( '<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				$sMessage
			);
			$this->loadAdminNoticesProcessor()->addRawAdminNotice( $sNoticeMessage, 'error' );
		}

		return $bConflicted;
	}

	/**
	 * @return bool
	 */
	protected function checkForUnsupportedConfiguration() {
		$oDp = $this->loadDataProcessor();
		$aRequestParts =  $oDp->getRequestUriParts();
		if ( $aRequestParts === false || empty( $aRequestParts['path'] ) )  {

			$sNoticeMessage = sprintf(
				'<strong>%s</strong>: %s',
				_wpsf__( 'Warning' ),
				_wpsf__( 'Your login URL is unchanged because your current hosting/PHP configuration cannot parse the necessary information.')
			);
			$this->loadAdminNoticesProcessor()->addRawAdminNotice( $sNoticeMessage, 'error' );
			return true;
		}
		return false;
	}

	/**
	 */
	public function doBlockPossibleWpLoginLoad() {

		// To begin, we block if it's an access to the admin area and the user isn't logged in (and it's not ajax)
		$bDoBlock = ( is_admin() && !is_user_logged_in() && !defined( 'DOING_AJAX' ) );

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$bDoBlock ) {
			$aRequestParts = $this->loadDataProcessor()->getRequestUriParts();
			$sPath = isset( $aRequestParts[ 'path' ] ) ? trim( $aRequestParts[ 'path' ], '/' ) : '';
			$sPath = preg_replace( '/(\/){2,}/', '/', $sPath );
			$aPossiblePaths = array(
				trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				// trim( site_url( 'wp-login.php', 'relative' ), '/' ), our own filters in run() scuttle us here so we have to build it manually
				trim( rtrim( site_url( '', 'relative' ), '/' ).'/wp-login.php', '/' ),
				trim( home_url( 'login', 'relative' ), '/' ),
				trim( site_url( 'login', 'relative' ), '/' )
			);
			$bDoBlock = !empty( $sPath )
				&& ( in_array( $sPath, $aPossiblePaths ) || preg_match( '/wp-login\.php/i', $sPath ));
		}

		if ( $bDoBlock ) {
			// We now black mark this IP
//			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
			$this->doWpLoginFailedRedirect404();
		}
	}

	/**
	 * @return string
	 */
	protected function getLoginPath() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		return $oFO->getCustomLoginPath();
	}

	/**
	 * @param string $sUrl
	 * @param string $sPath
	 * @return string
	 */
	public function fCheckForLoginPhp( $sUrl, $sPath ) {
		if ( strpos( $sUrl, 'wp-login.php' ) !== false ) {

			$sLoginUrl = home_url( $this->getLoginPath() );
			$aQueryArgs = explode( '?', $sUrl );
			if ( !empty( $aQueryArgs[1] ) ) {
				parse_str( $aQueryArgs[1], $aNewQueryArgs );
				$sLoginUrl = add_query_arg( $aNewQueryArgs, $sLoginUrl );
			}
			return $sLoginUrl;
		}
		return $sUrl;
	}

	/**
	 * @param string $sUrl
	 * @return string
	 */
	public function blockRegisterUrlRedirect( $sUrl ) {
		$aParts = $this->loadDataProcessor()->getRequestUriParts();
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
		if ( $this->loadWpFunctionsProcessor()->getIsLoginUrl() ) {
			@require_once( ABSPATH . 'wp-login.php' );
			die();
		}
	}

	public function aLoginFormAction() {
		if ( !$this->loadWpFunctionsProcessor()->getIsLoginUrl() ) {
			// We now black mark this IP
//			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
			$this->doWpLoginFailedRedirect404();
			die();
		}
	}

	/**
	 * Add the custom login URL to the Elegant Themes Maintenance Mode plugin URL exceptions list
	 *
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
				$this->loadWpFunctionsProcessor()->doRedirect( $sRedirectUrl, array(), false );
			}
		}

		$oDp = $this->loadDataProcessor();
		$sRequestUrl = $oDp->FetchServer( 'REQUEST_URI' );
		$oDp->doSendApache404(
			$sRequestUrl,
			$this->loadWpFunctionsProcessor()->getHomeUrl()
		);
	}

}
endif;
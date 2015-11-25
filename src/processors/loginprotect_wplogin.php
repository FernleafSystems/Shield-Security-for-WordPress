<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_WpLogin', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_WpLogin extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();

		if ( !$oFO->getIsCustomLoginPathEnabled() || $this->checkForPluginConflict() || $this->checkForUnsupportedConfiguration() ) {
			return false;
		}

		// Loads the wp-login.php is the correct URL is loaded
		add_action( 'init', array( $this, 'doBlockPossibleAutoRedirection' ) );

		// Loads the wp-login.php is the correct URL is loaded
		add_filter( 'wp_loaded', array( $this, 'aLoadWpLogin' ) );

		// kills the wp-login.php if it's being loaded by anything but the virtual URL
		add_action( 'login_init', array( $this, 'aLoginFormAction' ), 0 );

		// ensure that wp-login.php is never used in site urls or redirects
		add_filter( 'site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'network_site_url', array( $this, 'fCheckForLoginPhp' ), 20, 2 );
		add_filter( 'wp_redirect', array( $this, 'fCheckForLoginPhp' ), 20, 2 );

		add_filter( 'et_anticipate_exceptions', array( $this, 'fAddToEtMaintenanceExceptions' ) ) ;
		return true;
	}

	/**
	 * @return bool - true if conflict exists
	 */
	protected function checkForPluginConflict() {

		$sMessage = '';
		$bConflicted = false;

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $oWp->isMultisite() ) {
			$sMessage = _wpsf__( 'Your login URL is unchanged because the Rename WP Login feature is not currently supported on WPMS.' );
			$bConflicted = true;
		}
		else if ( class_exists( 'Rename_WP_Login', false ) ) {
			$sMessage = _wpsf__( 'Can not use the Rename WP Login feature because you have the "Rename WP Login" plugin installed and active.' );
			$bConflicted = true;
		}
		else if ( !$oWp->getIsPermalinksEnabled() ) {
			$sMessage = sprintf( _wpsf__( 'Can not use the Rename WP Login feature because you have not enabled %s.' ), __( 'Permalinks' ) );
			$bConflicted = true;
		}
		else if ( $oWp->getIsPermalinksEnabled() && $oWp->getDoesWpSlugExist( $this->getLoginPath() ) ) {
			$sMessage = _wpsf__( 'Can not use the Rename WP Login feature because you have chosen a path that is already reserved on your WordPress site.' );
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
	public function doBlockPossibleAutoRedirection() {

		// To begin, we block if it's an access to the admin area and the user isn't logged in (and it's not ajax)
		$bDoBlock = ( is_admin() && !is_user_logged_in() && !defined( 'DOING_AJAX' ) );

		// Next block option is where it's a direct attempt to access the old login URL
		if ( !$bDoBlock ) {
			$aRequestParts = $this->loadDataProcessor()->getRequestUriParts();
			$sPath = isset( $aRequestParts[ 'path' ] ) ? trim( $aRequestParts[ 'path' ], '/' ) : '';
			$aPossiblePaths = array(
				trim( home_url( 'wp-login.php', 'relative' ), '/' ),
				trim( site_url( 'wp-login.php', 'relative' ), '/' ),
				trim( home_url( 'login', 'relative' ), '/' ),
				trim( site_url( 'login', 'relative' ), '/' )
			);

			$bDoBlock = in_array( $sPath, $aPossiblePaths );
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
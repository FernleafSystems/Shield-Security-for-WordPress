<?php

class ICWP_WPSF_WpFunctions extends ICWP_WPSF_Foundation {

	/**
	 * @var WP_Automatic_Updater
	 */
	protected $oWpAutomaticUpdater;

	/**
	 * @var ICWP_WPSF_WpFunctions
	 */
	protected static $oInstance = null;

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @var string
	 */
	protected $sWpVersion;

	public function __construct() {
	}

	/**
	 * @return null|string
	 */
	public function findWpLoad() {
		return $this->findWpCoreFile( 'wp-load.php' );
	}

	/**
	 * @param $sFilename
	 * @return null|string
	 */
	public function findWpCoreFile( $sFilename ) {
		$sLoaderPath = __DIR__;
		$nLimiter = 0;
		$nMaxLimit = count( explode( DIRECTORY_SEPARATOR, trim( $sLoaderPath, DIRECTORY_SEPARATOR ) ) );
		$bFound = false;

		do {
			if ( @is_file( $sLoaderPath.DIRECTORY_SEPARATOR.$sFilename ) ) {
				$bFound = true;
				break;
			}
			$sLoaderPath = realpath( $sLoaderPath.DIRECTORY_SEPARATOR.'..' );
			$nLimiter++;
		} while ( $nLimiter < $nMaxLimit );

		return $bFound ? $sLoaderPath.DIRECTORY_SEPARATOR.$sFilename : null;
	}

	/**
	 * @param string $sRedirect
	 * @return bool
	 */
	public function doForceRunAutomaticUpdates( $sRedirect = '' ) {

		$lock_name = 'auto_updater.lock'; //ref: /wp-admin/includes/class-wp-upgrader.php
		delete_option( $lock_name );
		if ( !defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true ); // this prevents WP from disabling plugins pre-upgrade
		}

		// does the actual updating
		wp_maybe_auto_update();

		if ( !empty( $sRedirect ) ) {
			$this->doRedirect( network_admin_url( $sRedirect ) );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function isRunningAutomaticUpdates() {
		return ( get_option( 'auto_updater.lock' ) ? true : false );
	}

	/**
	 * The full plugin file to be upgraded.
	 * @param string $sPluginFile
	 * @return boolean
	 */
	public function doPluginUpgrade( $sPluginFile ) {
		$oWpPlugins = $this->loadWpPlugins();
		if ( !$oWpPlugins->isUpdateAvailable( $sPluginFile )
			 || ( isset( $GLOBALS[ 'pagenow' ] ) && $GLOBALS[ 'pagenow' ] == 'update.php' ) ) {
			return true;
		}
		wp_redirect( $oWpPlugins->getUrl_Upgrade( $sPluginFile ) );
		exit();
	}

	/**
	 * Clears any WordPress caches
	 */
	public function doBustCache() {
		global $_wp_using_ext_object_cache, $wp_object_cache;
		$_wp_using_ext_object_cache = false;
		if ( !empty( $wp_object_cache ) ) {
			@$wp_object_cache->flush();
		}
	}

	/**
	 * @return bool
	 */
	public function isPermalinksEnabled() {
		return ( $this->getOption( 'permalink_structure' ) ? true : false );
	}

	/**
	 * @see wp_redirect_admin_locations()
	 * @return array
	 */
	public function getAutoRedirectLocations() {
		return array( 'wp-admin', 'dashboard', 'admin', 'login', 'wp-login.php' );
	}

	/**
	 * @return string[]
	 */
	public function getCoreChecksums() {
		$aChecksumData = false;
		$sCurrentVersion = $this->getVersion();

		if ( function_exists( 'get_core_checksums' ) ) { // if it's loaded, we use it.
			$aChecksumData = get_core_checksums( $sCurrentVersion, $this->getLocaleForChecksums() );
		}
		else {
			$aQueryArgs = array(
				'version' => $sCurrentVersion,
				'locale'  => $this->getLocaleForChecksums()
			);
			$sQueryUrl = add_query_arg( $aQueryArgs, 'https://api.wordpress.org/core/checksums/1.0/' );
			$sResponse = $this->loadFS()->getUrlContent( $sQueryUrl );
			if ( !empty( $sResponse ) ) {
				$aDecodedResponse = json_decode( trim( $sResponse ), true );
				if ( is_array( $aDecodedResponse ) && isset( $aDecodedResponse[ 'checksums' ] ) && is_array( $aDecodedResponse[ 'checksums' ] ) ) {
					$aChecksumData = $aDecodedResponse[ 'checksums' ];
				}
			}
		}
		return is_array( $aChecksumData ) ? $aChecksumData : array();
	}

	/**
	 * @return array|false
	 */
	public function getCoreUpdates() {
		include_once( ABSPATH.'wp-admin/includes/update.php' );
		return get_core_updates();
	}

	/**
	 * @return string
	 */
	public function getDirUploads() {
		$aDirParts = wp_get_upload_dir();
		$bHasUploads = is_array( $aDirParts ) && !empty( $aDirParts[ 'basedir' ] )
					   && $this->loadFS()->exists( $aDirParts[ 'basedir' ] );
		return $bHasUploads ? $aDirParts[ 'basedir' ] : '';
	}

	/**
	 * @param string $sPath
	 * @return string
	 */
	public function getUrl_WpAdmin( $sPath = '' ) {
		return get_admin_url( null, $sPath );
	}

	/**
	 * @param string $sPath
	 * @return string
	 */
	public function getHomeUrl( $sPath = '' ) {
		$sUrl = home_url( $sPath );
		if ( empty( $sUrl ) ) {
			remove_all_filters( 'home_url' );
			$sUrl = home_url( $sPath );
		}
		return $sUrl;
	}

	/**
	 * @param bool $bRemoveSchema
	 * @return string
	 */
	public function getWpUrl( $bRemoveSchema = false ) {
		$sUrl = network_site_url();
		if ( empty( $sUrl ) ) {
			remove_all_filters( 'site_url' );
			remove_all_filters( 'network_site_url' );
			$sUrl = network_site_url();
		}
		if ( $bRemoveSchema ) {
			$sUrl = preg_replace( '#^((http|https):)?\/\/#i', '', $sUrl );
		}
		return $sUrl;
	}

	/**
	 * @param string $sSeparator
	 * @return string
	 */
	public function getLocale( $sSeparator = '_' ) {
		return str_replace( '_', $sSeparator, get_locale() );
	}

	/**
	 * @return string
	 */
	public function getLocaleForChecksums() {
		global $wp_local_package;
		return empty( $wp_local_package ) ? 'en_US' : $wp_local_package;
	}

	/**
	 * @param stdClass|string $mItem
	 * @param string          $sContext from plugin|theme
	 * @return string
	 */
	public function getFileFromAutomaticUpdateItem( $mItem, $sContext = 'plugin' ) {
		if ( is_object( $mItem ) && isset( $mItem->{$sContext} ) ) { // WP 3.8.2+
			$mItem = $mItem->{$sContext};
		}
		else if ( !is_string( $mItem ) ) { // WP pre-3.8.2
			$mItem = '';
		}
		return $mItem;
	}

	/**
	 * @return array
	 */
	public function getThemes() {
		if ( !function_exists( 'wp_get_themes' ) ) {
			require_once( ABSPATH.'wp-admin/includes/theme.php' );
		}
		return function_exists( 'wp_get_themes' ) ? wp_get_themes() : array();
	}

	/**
	 * @param string $sType - plugins, themes
	 * @return array
	 */
	public function getWordpressUpdates( $sType = 'plugins' ) {
		$oCurrent = $this->getTransient( 'update_'.$sType );
		return ( isset( $oCurrent->response ) && is_array( $oCurrent->response ) ) ? $oCurrent->response : array();
	}

	/**
	 * @return array
	 */
	public function getWordpressUpdates_Themes() {
		return $this->getWordpressUpdates( 'themes' );
	}

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function getTransient( $sKey ) {
		// TODO: Handle multisite

		if ( function_exists( 'get_site_transient' ) ) {
			$mResult = get_site_transient( $sKey );
			if ( empty( $mResult ) ) {
				remove_all_filters( 'pre_site_transient_'.$sKey );
				$mResult = get_site_transient( $sKey );
			}
		}
		else if ( version_compare( $this->getVersion(), '2.7.9', '<=' ) ) {
			$mResult = get_option( $sKey );
		}
		else if ( version_compare( $this->getVersion(), '2.9.9', '<=' ) ) {
			$mResult = apply_filters( 'transient_'.$sKey, get_option( '_transient_'.$sKey ) );
		}
		else {
			$mResult = apply_filters( 'site_transient_'.$sKey, get_option( '_site_transient_'.$sKey ) );
		}
		return $mResult;
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @param int    $nExpire
	 * @return bool
	 */
	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		return set_site_transient( $sKey, $mValue, $nExpire );
	}

	/**
	 * @param $sKey
	 * @return bool
	 */
	public function deleteTransient( $sKey ) {

		if ( version_compare( $this->getVersion(), '2.7.9', '<=' ) ) {
			$bResult = delete_option( $sKey );
		}
		else if ( function_exists( 'delete_site_transient' ) ) {
			$bResult = delete_site_transient( $sKey );
		}
		else if ( version_compare( $this->getVersion(), '2.9.9', '<=' ) ) {
			$bResult = delete_option( '_transient_'.$sKey );
		}
		else {
			$bResult = delete_option( '_site_transient_'.$sKey );
		}
		return $bResult;
	}

	/**
	 * @return string
	 */
	public function getVersion() {

		if ( empty( $this->sWpVersion ) ) {
			$sVersionFile = ABSPATH.WPINC.'/version.php';
			$sVersionContents = file_get_contents( $sVersionFile );

			if ( preg_match( '/wp_version\s=\s\'([^(\'|")]+)\'/i', $sVersionContents, $aMatches ) ) {
				$this->sWpVersion = $aMatches[ 1 ];
			}
			else {
				global $wp_version;
				$this->sWpVersion = $wp_version;
			}
		}
		return $this->sWpVersion;
	}

	/**
	 * @param string $sVersionToMeet
	 * @return boolean
	 */
	public function getWordpressIsAtLeastVersion( $sVersionToMeet ) {
		return version_compare( $this->getVersion(), $sVersionToMeet, '>=' );
	}

	/**
	 * @param string $sPluginBaseFilename
	 * @return boolean
	 */
	public function isPluginAutomaticallyUpdated( $sPluginBaseFilename ) {
		$oUpdater = $this->getWpAutomaticUpdater();
		if ( !$oUpdater ) {
			return false;
		}

		// This is due to a change in the filter introduced in version 3.8.2
		if ( $this->getWordpressIsAtLeastVersion( '3.8.2' ) ) {
			$mPluginItem = new stdClass();
			$mPluginItem->plugin = $sPluginBaseFilename;
		}
		else {
			$mPluginItem = $sPluginBaseFilename;
		}

		return $oUpdater->should_update( 'plugin', $mPluginItem, WP_PLUGIN_DIR );
	}

	/**
	 * @return bool
	 */
	public function canCoreUpdateAutomatically() {
		global $required_php_version, $required_mysql_version;
		$future_minor_update = (object)array(
			'current'       => $this->getVersion().'.1.next.minor',
			'version'       => $this->getVersion().'.1.next.minor',
			'php_version'   => $required_php_version,
			'mysql_version' => $required_mysql_version,
		);
		return $this->getWpAutomaticUpdater()
					->should_update( 'core', $future_minor_update, ABSPATH );
	}

	/**
	 * See: /wp-admin/update-core.php core_upgrade_preamble()
	 * @return bool
	 */
	public function hasCoreUpdate() {
		$aUpdates = $this->getCoreUpdates();
		return ( isset( $aUpdates[ 0 ]->response ) && 'latest' != $aUpdates[ 0 ]->response );
	}

	public function redirectHere() {
		$this->doRedirect( $this->loadRequest()->getUri() );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToLogin( $aQueryParams = array() ) {
		$this->doRedirect( wp_login_url(), $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToAdmin( $aQueryParams = array() ) {
		$this->doRedirect( is_multisite() ? get_admin_url() : admin_url(), $aQueryParams );
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToHome( $aQueryParams = array() ) {
		$this->doRedirect( home_url(), $aQueryParams );
	}

	/**
	 * @param string $sUrl
	 * @param array  $aQueryParams
	 * @param bool   $bSafe
	 * @param bool   $bProtectAgainstInfiniteLoops - if false, ignores the redirect loop protection
	 */
	public function doRedirect( $sUrl, $aQueryParams = array(), $bSafe = true, $bProtectAgainstInfiniteLoops = true ) {
		$sUrl = empty( $aQueryParams ) ? $sUrl : add_query_arg( $aQueryParams, $sUrl );

		$oReq = $this->loadRequest();
		// we prevent any repetitive redirect loops
		if ( $bProtectAgainstInfiniteLoops ) {
			if ( $oReq->cookie( 'icwp-isredirect' ) == 'yes' ) {
				return;
			}
			else {
				$oReq->setCookie( 'icwp-isredirect', 'yes', 5 );
			}
		}

		// based on: https://make.wordpress.org/plugins/2015/04/20/fixing-add_query_arg-and-remove_query_arg-usage/
		// we now escape the URL to be absolutely sure since we can't guarantee the URL coming through there
		$sUrl = esc_url_raw( $sUrl );
		$bSafe ? wp_redirect( $sUrl ) : wp_redirect( $sUrl );
		exit();
	}

	/**
	 * @return string
	 */
	public function getCurrentPage() {
		global $pagenow;
		return $pagenow;
	}

	/**
	 * @return WP_Post
	 */
	public function getCurrentPost() {
		global $post;
		return $post;
	}

	/**
	 * @return int
	 */
	public function getCurrentPostId() {
		$oPost = $this->getCurrentPost();
		return empty( $oPost->ID ) ? -1 : $oPost->ID;
	}

	/**
	 * @param $nPostId
	 * @return false|WP_Post
	 */
	public function getPostById( $nPostId ) {
		return WP_Post::get_instance( $nPostId );
	}

	/**
	 * @param string $sPageSlug
	 * @param bool   $bWpmsOnly
	 * @return string
	 */
	public function getUrl_AdminPage( $sPageSlug, $bWpmsOnly = false ) {
		$sUrl = sprintf( 'admin.php?page=%s', $sPageSlug );
		return $bWpmsOnly ? network_admin_url( $sUrl ) : admin_url( $sUrl );
	}

	/**
	 * @param string $sPath
	 * @param bool   $bWpmsOnly
	 * @return string
	 */
	public function getAdminUrl( $sPath = '', $bWpmsOnly = false ) {
		return $bWpmsOnly ? network_admin_url( $sPath ) : admin_url( $sPath );
	}

	/**
	 * @param bool $bWpmsOnly
	 * @return string
	 */
	public function getAdminUrl_Plugins( $bWpmsOnly = false ) {
		return $this->getAdminUrl( 'plugins.php', $bWpmsOnly );
	}

	/**
	 * @param bool $bWpmsOnly
	 * @return string
	 */
	public function getAdminUrl_Themes( $bWpmsOnly = false ) {
		return $this->getAdminUrl( 'themes.php', $bWpmsOnly );
	}

	/**
	 * @param bool $bWpmsOnly
	 * @return string
	 */
	public function getAdminUrl_Updates( $bWpmsOnly = false ) {
		return $this->getAdminUrl( 'update-core.php', $bWpmsOnly );
	}

	/**
	 * @return string
	 */
	public function getUrl_CurrentAdminPage() {

		$sPage = $this->getCurrentPage();
		$sUrl = self_admin_url( $sPage );

		//special case for plugin admin pages.
		if ( $sPage == 'admin.php' ) {
			$sSubPage = $this->loadRequest()->query( 'page' );
			if ( !empty( $sSubPage ) ) {
				$aQueryArgs = array(
					'page' => $sSubPage,
				);
				$sUrl = add_query_arg( $aQueryArgs, $sUrl );
			}
		}
		return $sUrl;
	}

	/**
	 * @param string
	 * @return string
	 */
	public function isCurrentPage( $sPage ) {
		return $sPage == $this->getCurrentPage();
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getIsPage_Updates() {
		return $this->isCurrentPage( 'update.php' );
	}

	/**
	 * @param string $sUrlPath
	 * @return bool
	 */
	public function isLoginUrl( $sUrlPath ) {
		$sLoginUrlPath = @parse_url( wp_login_url(), PHP_URL_PATH );
		return ( !empty( $sUrlPath ) && ( rtrim( $sUrlPath, '/' ) == rtrim( $sLoginUrlPath, '/' ) ) );
	}

	/**
	 * @return bool
	 */
	public function isRequestLoginUrl() {
		return $this->isLoginUrl( $this->loadRequest()->getPath() );
	}

	/**
	 * @return bool
	 */
	public function isRequestUserLogin() {
		$oReq = $this->loadRequest();
		return $this->isRequestLoginUrl() && $oReq->isMethodPost()
			   && !is_null( $oReq->post( 'log' ) ) && !is_null( $oReq->post( 'pwd' ) );
	}

	/**
	 * @return bool
	 */
	public function isRequestUserRegister() {
		$oReq = $this->loadRequest();
		return $oReq->isMethodPost() && !is_null( $oReq->post( 'user_login' ) )
			   && !is_null( $oReq->post( 'user_email' ) ) && $this->isRequestLoginUrl();
	}

	/**
	 * @return bool
	 */
	public function isRequestUserResetPasswordStart() {
		$oReq = $this->loadRequest();
		return $this->isRequestLoginUrl() && $oReq->isMethodPost() && !is_null( $oReq->post( 'user_login' ) );
	}

	/**
	 * @return int
	 */
	public function getAuthCookieExpiration() {
		return (int)apply_filters( 'auth_cookie_expiration', 14*DAY_IN_SECONDS, 0, false );
	}

	/**
	 * @param $sTermSlug
	 * @return bool
	 */
	public function getDoesWpSlugExist( $sTermSlug ) {
		return ( $this->getDoesWpPostSlugExist( $sTermSlug ) || term_exists( $sTermSlug ) );
	}

	/**
	 * @param $sTermSlug
	 * @return bool
	 */
	public function getDoesWpPostSlugExist( $sTermSlug ) {
		$oDb = $this->loadDbProcessor();
		$sQuery = "
				SELECT ID FROM %s
				WHERE post_name = '%s'
				LIMIT 1
			";
		$sQuery = sprintf(
			$sQuery,
			$oDb->getTable_Posts(),
			$sTermSlug
		);
		$nResult = $oDb->getVar( $sQuery );
		return !is_null( $nResult ) && $nResult > 0;
	}

	/**
	 * @return string
	 */
	public function getSiteName() {
		return function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'WordPress Site';
	}

	/**
	 * @return string
	 */
	public function getSiteAdminEmail() {
		return function_exists( 'get_bloginfo' ) ? get_bloginfo( 'admin_email' ) : '';
	}

	/**
	 * @return string
	 */
	public function getCookieDomain() {
		return defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : false;
	}

	/**
	 * @return string
	 */
	public function getCookiePath() {
		return defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
	}

	/**
	 * @return boolean
	 */
	public function isAjax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * @return boolean
	 */
	public function isCron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}

	/**
	 * @return boolean
	 */
	public function isXmlrpc() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	/**
	 * @return boolean
	 */
	public function isMobile() {
		return function_exists( 'wp_is_mobile' ) && wp_is_mobile();
	}

	/**
	 * @return array
	 */
	public function getAllUserLoginUsernames() {
		$aUsers = get_users( array( 'fields' => array( 'user_login' ) ) );
		$aLogins = array();
		foreach ( $aUsers as $oUser ) {
			$aLogins[] = $oUser->user_login;
		}
		return $aLogins;
	}

	/**
	 * @return bool
	 */
	public function isMultisite() {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * @return bool
	 */
	public function isMultisite_SubdomainInstall() {
		return $this->isMultisite() && defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL;
	}

	/**
	 * @return bool
	 */
	public function isRest() {
		$bIsRest = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || !empty( $_REQUEST[ 'rest_route' ] );

		global $wp_rewrite;
		if ( !$bIsRest && function_exists( 'rest_url' ) && is_object( $wp_rewrite ) ) {
			$sRestUrlBase = get_rest_url( get_current_blog_id(), '/' );
			$sRestPath = trim( parse_url( $sRestUrlBase, PHP_URL_PATH ), '/' );
			$sRequestPath = trim( $this->loadRequest()->getPath(), '/' );
			$bIsRest = !empty( $sRequestPath ) && !empty( $sRestPath )
					   && ( strpos( $sRequestPath, $sRestPath ) === 0 );
		}
		return $bIsRest;
	}

	/**
	 * @return string|null
	 */
	public function getRestNamespace() {
		$sNameSpace = null;

		$sPath = $this->getRestPath();

		if ( !empty( $sPath ) ) {
			$aParts = explode( '/', $sPath );
			if ( !empty( $aParts ) ) {
				$sNameSpace = $aParts[ 0 ];
			}
		}
		return $sNameSpace;
	}

	/**
	 * @return string|null
	 */
	public function getRestPath() {
		$sPath = null;

		if ( $this->isRest() ) {
			$oReq = $this->loadRequest();

			$sPath = $oReq->request( 'rest_route' );
			if ( empty( $sPath ) && $this->isPermalinksEnabled() ) {
				$sFullUri = $this->loadWp()->getHomeUrl( $oReq->getPath() );
				$sPath = substr( $sFullUri, strlen( get_rest_url( get_current_blog_id() ) ) );
			}
		}
		return $sPath;
	}

	/**
	 * @param string $sKey
	 * @param string $sValue
	 * @return bool
	 */
	public function addOption( $sKey, $sValue ) {
		return $this->isMultisite() ? add_site_option( $sKey, $sValue ) : add_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param        $sValue
	 * @return boolean
	 */
	public function updateOption( $sKey, $sValue ) {
		return $this->isMultisite() ? update_site_option( $sKey, $sValue ) : update_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	public function getOption( $sKey, $mDefault = false ) {
		return $this->isMultisite() ? get_site_option( $sKey, $mDefault ) : get_option( $sKey, $mDefault );
	}

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function deleteOption( $sKey ) {
		return $this->isMultisite() ? delete_site_option( $sKey ) : delete_option( $sKey );
	}

	/**
	 * @return string
	 */
	public function getCurrentWpAdminPage() {

		$oReq = $this->loadRequest();
		$sScript = $oReq->getScriptName();
		if ( is_admin() && !empty( $sScript ) && basename( $sScript ) == 'admin.php' ) {
			$sCurrentPage = $oReq->query( 'page' );
		}
		return empty( $sCurrentPage ) ? '' : $sCurrentPage;
	}

	/**
	 * @param int|null $nTime
	 * @param bool     $bShowTime
	 * @param bool     $bShowDate
	 * @return string
	 */
	public function getTimeStringForDisplay( $nTime = null, $bShowTime = true, $bShowDate = true ) {
		$nTime = empty( $nTime ) ? $this->loadRequest()->ts() : $nTime;

		$sFullTimeString = $bShowTime ? $this->getTimeFormat() : '';
		if ( empty( $sFullTimeString ) ) {
			$sFullTimeString = $bShowDate ? $this->getDateFormat() : '';
		}
		else {
			$sFullTimeString = $bShowDate ? ( $sFullTimeString.' '.$this->getDateFormat() ) : $sFullTimeString;
		}
		return date_i18n( $sFullTimeString, $this->getTimeAsGmtOffset( $nTime ) );
	}

	/**
	 * @param int|null $nTime
	 * @return string
	 */
	public function getTimeStampForDisplay( $nTime = null ) {
		$nTime = empty( $nTime ) ? $this->loadRequest()->ts() : $nTime;
		return date_i18n( DATE_RFC2822, $this->getTimeAsGmtOffset( $nTime ) );
	}

	/**
	 * @param int $nTime
	 * @return int
	 */
	public function getTimeAsGmtOffset( $nTime = null ) {

		$nTimezoneOffset = wp_timezone_override_offset();
		if ( $nTimezoneOffset === false ) {
			$nTimezoneOffset = $this->getOption( 'gmt_offset' );
			if ( empty( $nTimezoneOffset ) ) {
				$nTimezoneOffset = 0;
			}
		}

		$nTime = is_null( $nTime ) ? $this->loadRequest()->ts() : $nTime;
		return $nTime + ( $nTimezoneOffset*HOUR_IN_SECONDS );
	}

	/**
	 * @return string
	 */
	public function getTimeFormat() {
		$sFormat = $this->getOption( 'time_format' );
		if ( empty( $sFormat ) ) {
			$sFormat = 'H:i';
		}
		return $sFormat;
	}

	/**
	 * @return string
	 */
	public function getDateFormat() {
		$sFormat = $this->getOption( 'date_format' );
		if ( empty( $sFormat ) ) {
			$sFormat = 'F j, Y';
		}
		return $sFormat;
	}

	/**
	 * @return false|WP_Automatic_Updater
	 */
	public function getWpAutomaticUpdater() {
		if ( !isset( $this->oWpAutomaticUpdater ) ) {
			if ( !class_exists( 'WP_Automatic_Updater', false ) ) {
				include_once( ABSPATH.'wp-admin/includes/class-wp-upgrader.php' );
			}
			if ( class_exists( 'WP_Automatic_Updater', false ) ) {
				$this->oWpAutomaticUpdater = new WP_Automatic_Updater();
			}
			else {
				$this->oWpAutomaticUpdater = false;
			}
		}
		return $this->oWpAutomaticUpdater;
	}

	/**
	 * Flushes the Rewrite rules and forces a re-commit to the .htaccess where applicable
	 */
	public function resavePermalinks() {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		if ( is_object( $wp_rewrite ) ) {
			$wp_rewrite->flush_rules( true );
		}
	}

	/**
	 * @return bool
	 */
	public function turnOffCache() {
		if ( !defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		// WP Fastest Cache
		if ( isset( $GLOBALS[ 'wp_fastest_cache' ] ) and is_object( $GLOBALS[ 'wp_fastest_cache' ] )
														 && method_exists( $GLOBALS[ 'wp_fastest_cache' ], 'deleteCache' )
														 && is_callable( array(
				$GLOBALS[ 'wp_fastest_cache' ],
				'deleteCache'
			) )
		) {
			$GLOBALS[ 'wp_fastest_cache' ]->deleteCache(); //WpFastestCache
		}
		return DONOTCACHEPAGE;
	}

	/**
	 * @param string $sMessage
	 * @param string $sTitle
	 * @param bool   $bTurnOffCachePage
	 */
	public function wpDie( $sMessage, $sTitle = '', $bTurnOffCachePage = true ) {
		if ( $bTurnOffCachePage ) {
			$this->turnOffCache();
		}
		wp_die( $sMessage, $sTitle );
	}
}
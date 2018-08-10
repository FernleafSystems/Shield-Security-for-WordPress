<?php
if ( class_exists( 'ICWP_WPSF_Foundation', false ) ) {
	return;
}

class ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_DataProcessor
	 */
	private static $oDp;
	/**
	 * @var ICWP_WPSF_WpFilesystem
	 */
	private static $oFs;
	/**
	 * @var ICWP_WPSF_WpCron
	 */
	private static $oWpCron;
	/**
	 * @var ICWP_WPSF_WpFunctions
	 */
	private static $oWp;
	/**
	 * @var ICWP_WPSF_WpFunctions_Plugins
	 */
	private static $oWpPlugins;
	/**
	 * @var ICWP_WPSF_WpFunctions_Themes
	 */
	private static $oWpThemes;
	/**
	 * @var ICWP_WPSF_WpDb
	 */
	private static $oWpDb;
	/**
	 * @var ICWP_WPSF_Render
	 */
	private static $oRender;
	/**
	 * @var ICWP_WPSF_YamlProcessor
	 */
	private static $oYaml;
	/**
	 * @var ICWP_WPSF_Ip
	 */
	private static $oIp;
	/**
	 * @var ICWP_WPSF_Ssl
	 */
	private static $oSsl;
	/**
	 * @var ICWP_WPSF_GoogleAuthenticator
	 */
	private static $oGA;
	/**
	 * @var ICWP_WPSF_WpAdminNotices
	 */
	private static $oAdminNotices;
	/**
	 * @var ICWP_WPSF_WpUsers
	 */
	private static $oWpUsers;
	/**
	 * @var ICWP_WPSF_WpComments
	 */
	private static $oWpComments;
	/**
	 * @var ICWP_WPSF_GoogleRecaptcha
	 */
	private static $oGR;
	/**
	 * @var ICWP_WPSF_WpTrack
	 */
	private static $oTrack;
	/**
	 * @var ICWP_WPSF_Edd
	 */
	private static $oEdd;
	/**
	 * @var ICWP_WPSF_WpUpgrades
	 */
	private static $oUpgrades;
	/**
	 * @var ICWP_WPSF_GeoIp2
	 */
	private static $oGeoIp2;

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	static public function loadDP() {
		if ( !isset( self::$oDp ) ) {
			self::requireCommonLib( 'icwp-data.php' );
			self::$oDp = ICWP_WPSF_DataProcessor::GetInstance();
		}
		return self::$oDp;
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	static public function loadFS() {
		if ( !isset( self::$oFs ) ) {
			self::requireCommonLib( 'icwp-wpfilesystem.php' );
			self::$oFs = ICWP_WPSF_WpFilesystem::GetInstance();
		}
		return self::$oFs;
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	static public function loadWp() {
		if ( !isset( self::$oWp ) ) {
			self::requireCommonLib( 'icwp-wpfunctions.php' );
			self::$oWp = ICWP_WPSF_WpFunctions::GetInstance();
		}
		return self::$oWp;
	}

	/**
	 * @return ICWP_WPSF_WpFunctions_Plugins
	 */
	public function loadWpPlugins() {
		if ( ! isset( self::$oWpPlugins ) ) {
			self::requireCommonLib( 'icwp-wpfunctions-plugins.php' );
			self::$oWpPlugins = ICWP_WPSF_WpFunctions_Plugins::GetInstance();
		}

		return self::$oWpPlugins;
	}

	/**
	 * @return ICWP_WPSF_WpFunctions_Themes
	 */
	public function loadWpThemes() {
		if ( ! isset( self::$oWpThemes ) ) {
			self::requireCommonLib( 'icwp-wpfunctions-themes.php' );
			self::$oWpThemes = ICWP_WPSF_WpFunctions_Themes::GetInstance();
		}

		return self::$oWpThemes;
	}

	/**
	 * @return ICWP_WPSF_WpCron
	 */
	static public function loadWpCronProcessor() {
		if ( !isset( self::$oWpCron ) ) {
			self::requireCommonLib( 'icwp-wpcron.php' );
			self::$oWpCron = ICWP_WPSF_WpCron::GetInstance();
		}
		return self::$oWpCron;
	}

	/**
	 * @return ICWP_WPSF_WpUpgrades
	 */
	static public function loadWpUpgrades() {
		if ( ! isset( self::$oUpgrades ) ) {
			self::requireCommonLib( 'icwp-wpupgrades.php' );
			self::$oUpgrades = ICWP_WPSF_WpUpgrades::GetInstance();
		}

		return self::$oUpgrades;
	}

	/**
	 * @return void
	 */
	static public function loadWpWidgets() {
		self::requireCommonLib( 'wp-widget.php' );
	}

	/**
	 * @return ICWP_WPSF_WpDb
	 */
	static public function loadDbProcessor() {
		if ( !isset( self::$oWpDb ) ) {
			self::requireCommonLib( 'icwp-wpdb.php' );
			self::$oWpDb = ICWP_WPSF_WpDb::GetInstance();
		}
		return self::$oWpDb;
	}

	/**
	 * @return ICWP_WPSF_Ip
	 */
	static public function loadIpService() {
		if ( !isset( self::$oIp ) ) {
			self::requireCommonLib( 'icwp-ip.php' );
			self::$oIp = ICWP_WPSF_Ip::GetInstance();
		}
		return self::$oIp;
	}

	/**
	 * @return ICWP_WPSF_Ssl
	 */
	public function loadSslService() {
		if ( !isset( self::$oSsl ) ) {
			self::requireCommonLib( 'icwp-ssl.php' );
			self::$oSsl = ICWP_WPSF_Ssl::GetInstance();
		}
		return self::$oSsl;
	}

	/**
	 * @return ICWP_WPSF_GoogleAuthenticator
	 */
	static public function loadGoogleAuthenticatorProcessor() {
		if ( !isset( self::$oGA ) ) {
			self::requireCommonLib( 'icwp-googleauthenticator.php' );
			self::$oGA = ICWP_WPSF_GoogleAuthenticator::GetInstance();
		}
		return self::$oGA;
	}

	/**
	 * @return ICWP_WPSF_GoogleRecaptcha
	 */
	static public function loadGoogleRecaptcha() {
		if ( !isset( self::$oGR ) ) {
			self::requireCommonLib( 'icwp-googlearecaptcha.php' );
			self::$oGR = ICWP_WPSF_GoogleRecaptcha::GetInstance();
		}
		return self::$oGR;
	}

	/**
	 * @return ICWP_WPSF_WpIncludes
	 */
	static public function loadWpIncludes() {
		self::requireCommonLib( 'icwp-wpincludes.php' );
		return ICWP_WPSF_WpIncludes::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpTrack
	 */
	static public function loadWpTrack() {
		if ( !isset( self::$oTrack ) ) {
			self::requireCommonLib( 'wp-track.php' );
			self::$oTrack = ICWP_WPSF_WpTrack::GetInstance();
		}
		return self::$oTrack;
	}

	/**
	 */
	static public function loadFactory() {
		self::requireCommonLib( 'icwp-factory.php' );
	}

	/**
	 * @param string $sTemplatePath
	 * @return ICWP_WPSF_Render
	 */
	static public function loadRenderer( $sTemplatePath = '' ) {
		if ( !isset( self::$oRender ) ) {
			self::requireCommonLib( 'icwp-render.php' );
			self::$oRender = ICWP_WPSF_Render::GetInstance()
											 ->setAutoloaderPath( dirname( __FILE__ ).'/Twig'.DIRECTORY_SEPARATOR.'Autoloader.php' );
		}
		if ( !empty( $sTemplatePath ) ) {
			self::$oRender->setTemplateRoot( $sTemplatePath );
		}
		return ( clone self::$oRender );
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	static public function loadYamlProcessor() {
		if ( !isset( self::$oYaml ) ) {
			self::requireCommonLib( 'icwp-yaml.php' );
			self::$oYaml = ICWP_WPSF_YamlProcessor::GetInstance();
		}
		return self::$oYaml;
	}

	/**
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	static public function loadAdminNoticesProcessor() {
		if ( !isset( self::$oAdminNotices ) ) {
			self::requireCommonLib( 'wp-admin-notices.php' );
			self::$oAdminNotices = ICWP_WPSF_WpAdminNotices::GetInstance();
		}
		return self::$oAdminNotices;
	}

	/**
	 * @return ICWP_WPSF_WpUsers
	 */
	static public function loadWpUsers() {
		if ( !isset( self::$oWpUsers ) ) {
			self::requireCommonLib( 'wp-users.php' );
			self::$oWpUsers = ICWP_WPSF_WpUsers::GetInstance();
		}
		return self::$oWpUsers;
	}

	/**
	 * @return ICWP_WPSF_WpComments
	 */
	static public function loadWpComments() {
		if ( !isset( self::$oWpComments ) ) {
			self::requireCommonLib( 'wp-comments.php' );
			self::$oWpComments = ICWP_WPSF_WpComments::GetInstance();
		}
		return self::$oWpComments;
	}

	/**
	 * @return ICWP_WPSF_GeoIp2
	 */
	static public function loadGeoIp2() {
		if ( !isset( self::$oGeoIp2 ) ) {
			self::requireCommonLib( 'icwp-geoip2.php' );
			self::$oGeoIp2 = ICWP_WPSF_GeoIp2::GetInstance();
		}
		return self::$oGeoIp2;
	}

	/**
	 * @return ICWP_WPSF_Edd
	 */
	static public function loadEdd() {
		if ( !isset( self::$oEdd ) ) {
			self::requireCommonLib( 'icwp-edd.php' );
			self::$oEdd = ICWP_WPSF_Edd::GetInstance();
		}
		return self::$oEdd;
	}

	/**
	 */
	static public function loadAutoload() {
		self::requireCommonLib( 'lib/vendor/autoload.php' );
	}

	/**
	 * @param string $sFile
	 */
	static public function requireCommonLib( $sFile ) {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.$sFile );
	}

	/**
	 * @deprecated
	 * @return ICWP_WPSF_WpComments
	 */
	static public function loadWpCommentsProcessor() {
		return self::loadWpComments();
	}

	/**
	 * @deprecated
	 * @return ICWP_WPSF_DataProcessor
	 */
	static public function loadDataProcessor() {
		return self::loadDP();
	}
}
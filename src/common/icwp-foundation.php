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
	 * @return ICWP_WPSF_DataProcessor
	 */
	static public function loadDataProcessor() {
		if ( !isset( self::$oDp ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-data.php' );
			self::$oDp = ICWP_WPSF_DataProcessor::GetInstance();
		}
		return self::$oDp;
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	static public function loadFS() {
		if ( !isset( self::$oFs ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-wpfilesystem.php' );
			self::$oFs = ICWP_WPSF_WpFilesystem::GetInstance();
		}
		return self::$oFs;
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	static public function loadWp() {
		if ( !isset( self::$oWp ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-wpfunctions.php' );
			self::$oWp = ICWP_WPSF_WpFunctions::GetInstance();
		}
		return self::$oWp;
	}

	/**
	 * TODO: Remove
	 * @alias
	 * @return ICWP_WPSF_WpFunctions
	 */
	static public function loadWpFunctions() {
		return self::loadWp();
	}

	/**
	 * @return ICWP_WPSF_WpCron
	 */
	static public function loadWpCronProcessor() {
		if ( !isset( self::$oWpCron ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-wpcron.php' );
			self::$oWpCron = ICWP_WPSF_WpCron::GetInstance();
		}
		return self::$oWpCron;
	}

	/**
	 * @return void
	 */
	static public function loadWpWidgets() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'wp-widget.php' );
	}

	/**
	 * @return ICWP_WPSF_WpDb
	 */
	static public function loadDbProcessor() {
		if ( !isset( self::$oWpDb ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-wpdb.php' );
			self::$oWpDb = ICWP_WPSF_WpDb::GetInstance();
		}
		return self::$oWpDb;
	}

	/**
	 * @return ICWP_WPSF_Ip
	 */
	static public function loadIpService() {
		if ( !isset( self::$oIp ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-ip.php' );
			self::$oIp = ICWP_WPSF_Ip::GetInstance();
		}
		return self::$oIp;
	}

	/**
	 * @return ICWP_WPSF_GoogleAuthenticator
	 */
	static public function loadGoogleAuthenticatorProcessor() {
		if ( !isset( self::$oGA ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-googleauthenticator.php' );
			self::$oGA = ICWP_WPSF_GoogleAuthenticator::GetInstance();
		}
		return self::$oGA;
	}

	/**
	 * @return ICWP_WPSF_GoogleRecaptcha
	 */
	static public function loadGoogleRecaptcha() {
		if ( !isset( self::$oGR ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-googlearecaptcha.php' );
			self::$oGR = ICWP_WPSF_GoogleRecaptcha::GetInstance();
		}
		return self::$oGR;
	}

	/**
	 * @return ICWP_WPSF_WpTrack
	 */
	static public function loadWpTrack() {
		if ( !isset( self::$oTrack ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'wp-track.php' );
			self::$oTrack = ICWP_WPSF_WpTrack::GetInstance();
		}
		return self::$oTrack;
	}

	/**
	 */
	static public function loadFactory() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-factory.php' );
	}

	/**
	 * @param string $sTemplatePath
	 * @return ICWP_WPSF_Render
	 */
	static public function loadRenderer( $sTemplatePath = '' ) {
		if ( !isset( self::$oRender ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-render.php' );
			self::$oRender = ICWP_WPSF_Render::GetInstance()
											 ->setAutoloaderPath( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'Twig'.DIRECTORY_SEPARATOR.'Autoloader.php' );
		}
		if ( !empty( $sTemplatePath ) ) {
			self::$oRender->setTemplateRoot( $sTemplatePath );
		}
		return self::$oRender;
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	static public function loadYamlProcessor() {
		if ( !isset( self::$oYaml ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-yaml.php' );
			self::$oYaml = ICWP_WPSF_YamlProcessor::GetInstance();
		}
		return self::$oYaml;
	}

	/**
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	static public function loadAdminNoticesProcessor() {
		if ( !isset( self::$oAdminNotices ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'wp-admin-notices.php' );
			self::$oAdminNotices = ICWP_WPSF_WpAdminNotices::GetInstance();
		}
		return self::$oAdminNotices;
	}

	/**
	 * @return ICWP_WPSF_WpUsers
	 */
	static public function loadWpUsers() {
		if ( !isset( self::$oWpUsers ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'wp-users.php' );
			self::$oWpUsers = ICWP_WPSF_WpUsers::GetInstance();
		}
		return self::$oWpUsers;
	}

	/**
	 * @return ICWP_WPSF_WpComments
	 */
	static public function loadWpCommentsProcessor() {
		if ( !isset( self::$oWpComments ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'wp-comments.php' );
			self::$oWpComments = ICWP_WPSF_WpComments::GetInstance();
		}
		return self::$oWpComments;
	}

	/**
	 * @return ICWP_WPSF_Edd
	 */
	static public function loadEdd() {
		if ( !isset( self::$oEdd ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-edd.php' );
			self::$oEdd = ICWP_WPSF_Edd::GetInstance();
		}
		return self::$oEdd;
	}

	/**
	 */
	static public function loadLib_Carbon() {
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'lib'
					  .DIRECTORY_SEPARATOR.'Carbon'.DIRECTORY_SEPARATOR.'Carbon.php' );
	}
}
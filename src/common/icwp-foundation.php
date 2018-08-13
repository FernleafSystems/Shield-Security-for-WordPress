<?php
if ( class_exists( 'ICWP_WPSF_Foundation', false ) ) {
	return;
}

class ICWP_WPSF_Foundation {

	/**
	 * @var array
	 */
	private static $aDic;

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	static public function loadDP() {
		$sKey = 'icwp-data';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_DataProcessor::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	static public function loadFS() {
		$sKey = 'icwp-wpfilesystem';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpFilesystem::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	static public function loadWp() {
		$sKey = 'icwp-wpfunctions';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpFunctions::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpFunctions_Plugins
	 */
	public function loadWpPlugins() {
		$sKey = 'icwp-wpfunctions-plugins';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpFunctions_Plugins::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpFunctions_Themes
	 */
	public function loadWpThemes() {
		$sKey = 'icwp-wpfunctions-themes';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpFunctions_Themes::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpCron
	 */
	static public function loadWpCronProcessor() {
		$sKey = 'icwp-wpcron';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpCron::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpUpgrades
	 */
	static public function loadWpUpgrades() {
		$sKey = 'icwp-wpupgrades';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpUpgrades::GetInstance() );
		}
		return self::getService( $sKey );
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
		$sKey = 'icwp-wpdb';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpDb::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Ip
	 */
	static public function loadIpService() {
		$sKey = 'icwp-ip';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_Ip::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Ssl
	 */
	public function loadSslService() {
		$sKey = 'icwp-ssl';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_Ssl::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_GoogleAuthenticator
	 */
	static public function loadGoogleAuthenticatorProcessor() {
		$sKey = 'icwp-googleauthenticator';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_GoogleAuthenticator::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_GoogleRecaptcha
	 */
	static public function loadGoogleRecaptcha() {
		$sKey = 'icwp-googlearecaptcha';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_GoogleRecaptcha::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpIncludes
	 */
	static public function loadWpIncludes() {
		$sKey = 'icwp-wpincludes';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpIncludes::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpTrack
	 */
	static public function loadWpTrack() {
		$sKey = 'wp-track';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpTrack::GetInstance() );
		}
		return self::getService( $sKey );
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
		$sKey = 'icwp-render';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			$oR = ICWP_WPSF_Render::GetInstance()
								  ->setAutoloaderPath( dirname( __FILE__ ).'/Twig/Autoloader.php' );
			self::setService( $sKey, $oR );
		}

		$oR = self::getService( $sKey );
		if ( !empty( $sTemplatePath ) ) {
			$oR->setTemplateRoot( $sTemplatePath );
		}
		return ( clone $oR );
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	static public function loadYamlProcessor() {
		$sKey = 'icwp-yaml';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_YamlProcessor::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	static public function loadAdminNoticesProcessor() {
		$sKey = 'wp-admin-notices';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpAdminNotices::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpUsers
	 */
	static public function loadWpUsers() {
		$sKey = 'wp-users';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpUsers::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpComments
	 */
	static public function loadWpComments() {
		$sKey = 'wp-comments';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_WpComments::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_GeoIp2
	 */
	static protected function loadGeoIp2() {
		$sKey = 'icwp-geoip2';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_GeoIp2::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Edd
	 */
	static public function loadEdd() {
		$sKey = 'icwp-edd';
		if ( !self::isServiceReady( $sKey ) ) {
			self::requireCommonLib( $sKey.'.php' );
			self::setService( $sKey, ICWP_WPSF_Edd::GetInstance() );
		}
		return self::getService( $sKey );
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
	 * @return array
	 */
	static private function getDic() {
		if ( !is_array( self::$aDic ) ) {
			self::$aDic = array();
		}
		return self::$aDic;
	}

	/**
	 * @param string $sService
	 * @return mixed
	 */
	static private function getService( $sService ) {
		$aDic = self::getDic();
		return $aDic[ $sService ];
	}

	/**
	 * @param string $sService
	 * @return bool
	 */
	static private function isServiceReady( $sService ) {
		$aDic = self::getDic();
		return !empty( $aDic[ $sService ] );
	}

	/**
	 * @param string $sServiceKey
	 * @param mixed  $oService
	 */
	static private function setService( $sServiceKey, $oService ) {
		$aDic = self::getDic();
		$aDic[ $sServiceKey ] = $oService;
		self::$aDic = $aDic;
	}
}
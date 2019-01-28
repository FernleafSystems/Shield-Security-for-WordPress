<?php

class ICWP_WPSF_Foundation {

	const DEFAULT_SERVICE_PREFIX = 'icwp_wpsf_';

	/**
	 * @var array
	 */
	private static $aDic;

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	protected function prefix( $sSuffix ) {
		return self::DEFAULT_SERVICE_PREFIX.$sSuffix;
	}

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	static public function loadDP() {
		$sKey = 'icwp-data';
		if ( !self::isServiceReady( $sKey ) ) {
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
			self::setService( $sKey, ICWP_WPSF_WpUpgrades::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_WpDb
	 */
	static public function loadDbProcessor() {
		$sKey = 'icwp-wpdb';
		if ( !self::isServiceReady( $sKey ) ) {
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
			self::setService( $sKey, ICWP_WPSF_Ip::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Request
	 */
	public function loadRequest() {
		$sKey = 'icwp-request';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, ICWP_WPSF_Request::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_ServiceProviders
	 */
	public function loadServiceProviders() {
		$sKey = 'icwp-serviceproviders';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, ICWP_WPSF_ServiceProviders::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Ssl
	 */
	public function loadSslService() {
		$sKey = 'icwp-ssl';
		if ( !self::isServiceReady( $sKey ) ) {
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
			self::setService( $sKey, ICWP_WPSF_WpIncludes::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @param string $sTemplatePath
	 * @return ICWP_WPSF_Render
	 */
	static public function loadRenderer( $sTemplatePath = '' ) {
		$sKey = 'icwp-render';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, ICWP_WPSF_Render::GetInstance() );
		}

		/** @var ICWP_WPSF_Render $oR */
		$oR = self::getService( $sKey );
		if ( !empty( $sTemplatePath ) ) {
			$oR->setTemplateRoot( $sTemplatePath );
		}
		return ( clone $oR );
	}

	/**
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	static public function loadWpNotices() {
		$sKey = 'wp-admin-notices';
		if ( !self::isServiceReady( $sKey ) ) {
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
			self::setService( $sKey, ICWP_WPSF_WpComments::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return ICWP_WPSF_Edd
	 */
	static public function loadEdd() {
		$sKey = 'icwp-edd';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, ICWP_WPSF_Edd::GetInstance() );
		}
		return self::getService( $sKey );
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

	/**
	 * @deprecated
	 * @return ICWP_WPSF_WpAdminNotices
	 */
	static public function loadAdminNoticesProcessor() {
		return self::loadWpNotices();
	}
}
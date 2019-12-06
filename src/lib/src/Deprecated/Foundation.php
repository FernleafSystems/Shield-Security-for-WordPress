<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Deprecated;

class Foundation {

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
	 * @return \ICWP_WPSF_WpCron
	 * @deprecated
	 */
	public static function loadWpCronProcessor() {
		$sKey = 'icwp-wpcron';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, \ICWP_WPSF_WpCron::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return \ICWP_WPSF_ServiceProviders
	 */
	public function loadServiceProviders() {
		$sKey = 'icwp-serviceproviders';
		if ( !self::isServiceReady( $sKey ) ) {
			self::setService( $sKey, \ICWP_WPSF_ServiceProviders::GetInstance() );
		}
		return self::getService( $sKey );
	}

	/**
	 * @return array
	 */
	private static function getDic() {
		if ( !is_array( self::$aDic ) ) {
			self::$aDic = [];
		}
		return self::$aDic;
	}

	/**
	 * @param string $sService
	 * @return mixed
	 */
	private static function getService( $sService ) {
		$aDic = self::getDic();
		return $aDic[ $sService ];
	}

	/**
	 * @param string $sService
	 * @return bool
	 */
	private static function isServiceReady( $sService ) {
		$aDic = self::getDic();
		return !empty( $aDic[ $sService ] );
	}

	/**
	 * @param string $sServiceKey
	 * @param mixed  $oService
	 */
	private static function setService( $sServiceKey, $oService ) {
		$aDic = self::getDic();
		$aDic[ $sServiceKey ] = $oService;
		self::$aDic = $aDic;
	}
}
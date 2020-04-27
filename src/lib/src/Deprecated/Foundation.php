<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Deprecated;

/**
 * Class Foundation
 * @package FernleafSystems\Wordpress\Plugin\Shield\Deprecated
 * @deprecated 9.0
 */
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
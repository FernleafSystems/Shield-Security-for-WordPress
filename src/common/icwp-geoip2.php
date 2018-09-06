<?php

if ( class_exists( 'ICWP_WPSF_GeoIp2', false ) ) {
	return;
}

/**
 */
class ICWP_WPSF_GeoIp2 extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_GeoIp2
	 */
	protected static $oInstance = null;

	/**
	 * @var \GeoIp2\Database\Reader
	 */
	private $oReader;

	/**
	 * @return ICWP_WPSF_GeoIp2
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sIP
	 * @return string
	 */
	public function countryName( $sIP ) {
		$sCountry = '';
		$oCountry = $this->getRegisteredCountry( $sIP );
		if ( !is_null( $oCountry ) ) {
			$sLoc = explode( '-', $this->loadWp()->getLocale() )[ 0 ];
			$sCountry = isset( $oCountry->names[ $sLoc ] ) ? $oCountry->names[ $sLoc ] : $oCountry->name;
		}
		return $sCountry;
	}

	/**
	 * @param string $sIP
	 * @return string
	 */
	public function countryIso( $sIP ) {
		$sIso = '';
		$oCountry = $this->getRegisteredCountry( $sIP );
		if ( !is_null( $oCountry ) ) {
			$sIso = $oCountry->isoCode;
		}
		return $sIso;
	}

	/**
	 * @param string $sIP
	 * @return \GeoIp2\Record\Country|null
	 */
	public function getRegisteredCountry( $sIP ) {
		$oCountry = null;
		if ( $this->isReady() ) {
			try {
				$oCountry = $this->getReader()
								 ->country( $sIP )->registeredCountry;
			}
			catch ( Exception $oe ) {
			}
		}
		return $oCountry;
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return ( $this->getReader() !== false );
	}

	/**
	 * @return \GeoIp2\Database\Reader|false
	 */
	protected function getReader() {
		if ( !isset( $this->oReader ) ) {
			try {
				$this->oReader = new \GeoIp2\Database\Reader( __DIR__.'/Components/GeoIp2/GeoLite2-Country.mmdb' );
			}
			catch ( Exception $oE ) {
				$this->oReader = false;
			}
		}
		return $this->oReader;
	}
}
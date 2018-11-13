<?php

require_once( __DIR__.'/ICWP_WPSF_BaseEntryVO.php' );

/**
 * Class ICWP_WPSF_ScannerEntryVO
 * @property string hash
 * @property array  data
 * @property string scan
 * @property string description
 * @property int    severity
 * @property int    discovered_at
 * @property int    ignored_until
 */
class ICWP_WPSF_ScannerEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function __get( $sKey ) {
		$mVal = parent::__get( $sKey );

		switch ( $sKey ) {
			case 'data':
				if ( is_string( $mVal ) && strpos( $mVal, '{' ) === 0 ) {
					$mVal = json_decode( $mVal, true );
				}
				break;
			default:
				break;
		}

		return $mVal;
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function __set( $sKey, $mValue ) {
		switch ( $sKey ) {
			case 'data':
				if ( !is_string( $mValue ) || strpos( $mValue, '{' ) === false ) {
					$mValue = json_encode( $mValue );
				}
				break;
			default:
				break;
		}

		parent::__set( $sKey, $mValue );
		return $this;
	}
}
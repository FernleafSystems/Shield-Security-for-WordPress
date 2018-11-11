<?php

require_once( __DIR__.'/ICWP_WPSF_BaseEntryVO.php' );

/**
 * Class ICWP_WPSF_ScannerEntryVO
 * @property string hash
 * @property array  data
 * @property string scan
 * @property string description
 * @property int    severity
 * @property int    ignored_at
 * @property int    repaired_at
 */
class ICWP_WPSF_ScannerEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function __get( $sKey ) {
		$mVal = null;
		switch ( $sKey ) {
			case 'data':
				$mVal = json_decode( parent::__get( $sKey ), true );
				break;
			default:
				$mVal = parent::__get( $sKey );
				break;
		}
		return $mVal;
	}
}
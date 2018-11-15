<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseEntryVO;

/**
 * Class ICWP_WPSF_ScannerEntryVO
 * @property string hash
 * @property array  data
 * @property string scan
 * @property string description
 * @property int    severity
 * @property int    discovered_at
 * @property int    ignored_at
 * @property int    updated_at
 */
class EntryVO extends BaseEntryVO {

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
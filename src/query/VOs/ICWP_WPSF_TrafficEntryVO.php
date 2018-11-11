<?php

/**
 * Class ICWP_WPSF_LiveTrafficEntryVO
 * @property string id
 * @property string rid
 * @property int    uid
 * @property string ip
 * @property string path
 * @property string code
 * @property string ua
 * @property string verb
 * @property bool   trans
 * @property int    created_at
 * @property int    deleted_at
 */
class ICWP_WPSF_TrafficEntryVO extends ICWP_WPSF_BaseEntryVO {

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {
		switch ( $sProperty ) {

			case 'ip':
				$mVal = inet_ntop( parent::__get( $sProperty ) );
				break;

			default:
				$mVal = parent::__get( $sProperty );
		}
		return $mVal;
	}
}
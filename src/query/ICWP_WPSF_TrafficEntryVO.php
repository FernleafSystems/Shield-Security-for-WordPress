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
class ICWP_WPSF_TrafficEntryVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter {
		__get as __parentGet;
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {
		switch ( $sProperty ) {

			case 'ip':
				$mVal = $this->getIp();
				break;

			default:
				$mVal = $this->__parentGet( $sProperty );
		}
		return $mVal;
	}

	/**
	 * @return string
	 */
	public function getIp() {
		return inet_ntop( $this->getParam( 'ip' ) );
	}
}
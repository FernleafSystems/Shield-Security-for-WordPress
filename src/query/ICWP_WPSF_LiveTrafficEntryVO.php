<?php

/**
 * Class ICWP_WPSF_LiveTrafficEntryVO
 * @property string id
 * @property int    uid
 * @property string ip
 * @property string path
 * @property string code
 * @property string ref
 * @property string ua
 * @property string verb
 * @property array  payload
 * @property int    created_at
 * @property int    deleted_at
 */
class ICWP_WPSF_LiveTrafficEntryVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter {
		__get as __parentGet;
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mVal = null;
		switch ( $sProperty ) {

			case 'ip':
				$mVal = $this->getIp();
				break;

			case 'payload':
				$mVal = $this->getPayload();
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

	/**
	 * @return array
	 */
	public function getPayload() {
		return json_decode( $this->getParam( 'ip' ), true );
	}
}
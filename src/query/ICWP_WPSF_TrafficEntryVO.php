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
 * @property array  payload
 * @property array  payload_get
 * @property array  payload_post
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

			case 'payload':
				$mVal = $this->getPayload();
				break;

			case 'payload_get':
				$mVal = $this->getPayloadGet();
				break;

			case 'payload_post':
				$mVal = $this->getPayloadPost();
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
		$aPayload = json_decode( $this->getParam( 'payload' ), true );
		if ( !is_array( $aPayload ) ) {
			$aPayload = array();
		}
		return $aPayload;
	}

	/**
	 * @return array
	 */
	protected function getPayloadGet() {
		$aP = $this->payload;
		return ( isset( $aP[ 'get' ] ) && is_array( $aP[ 'get' ] ) ) ? $aP[ 'get' ] : array();
	}

	/**
	 * @return array
	 */
	protected function getPayloadPost() {
		$aP = $this->payload;
		return ( isset( $aP[ 'post' ] ) && is_array( $aP[ 'post' ] ) ) ? $aP[ 'post' ] : array();
	}
}
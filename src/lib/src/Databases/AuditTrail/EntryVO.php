<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 *
 * @property string ip
 * @property string message
 * @property string wp_username
 * @property string rid
 * @property string event
 * @property string context
 * @property string category
 * @property string data - do not access directly! Instead getAuditData()
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @return array
	 */
	public function getAuditData() {
		$aData = array();
		if ( is_string( $this->data ) ) {
			$sData = base64_decode( $this->data, true );
			if ( !empty( $sData ) ) {
				$aData = @json_decode( $sData, true );
			}
		}
		return is_array( $aData ) ? $aData : [];
	}

	/**
	 * @param array $aData
	 * @return $this
	 */
	public function setAuditData( $aData ) {
		if ( !is_array( $aData ) ) {
			$aData = array();
		}
		$this->data = base64_encode( json_encode( $aData ) );
		return $this;
	}
}
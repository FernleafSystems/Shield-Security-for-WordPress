<?php

if ( class_exists( 'ICWP_WPSF_Query_Statistics_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base_select.php' );

class ICWP_WPSF_Query_Statistics_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function filterByParentStatKey( $sKey ) {
		return $this->addWhereEquals( 'parent_stat_key', $sKey );
	}

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function filterByStatKey( $sKey ) {
		return $this->addWhereEquals( 'stat_key', $sKey );
	}

	/**
	 * @param string $sStatKey
	 * @param string $sParentStatKey
	 * @return ICWP_WPSF_StatVO|stdClass|null
	 */
	public function retrieveStat( $sStatKey, $sParentStatKey = '' ) {
		if ( !empty( $sParentStatKey ) ) {
			$this->filterByParentStatKey( $sParentStatKey );
		}
		$oR = $this->filterByStatKey( $sStatKey )
				   ->setOrderBy( 'created_at', 'DESC' )
				   ->first();
		return $oR;
	}

	/**
	 * @return ICWP_WPSF_StatVO[]|stdClass[]
	 */
	public function query() {
		$aData = parent::query();
		if ( $this->isResultsAsVo() ) {
			$aData = array_map(
				function ( $oResult ) {
					return ( new ICWP_WPSF_StatVO() )->setRawData( $oResult );
				},
				$aData
			);
		}
		return $aData;
	}

	protected function customInit() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_StatVO.php' );
	}
}
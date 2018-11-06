<?php

if ( class_exists( 'ICWP_WPSF_Query_Tally_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/select.php' );

class ICWP_WPSF_Query_Tally_Select extends ICWP_WPSF_Query_BaseSelect {

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
	 * @return ICWP_WPSF_TallyVO|stdClass|null
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
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_TallyVO';
	}
}
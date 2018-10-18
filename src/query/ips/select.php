<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_Ips_Select extends ICWP_WPSF_Query_BaseSelect {

	protected function customInit() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_IpsEntryVO.php' );
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		return $this->addWhereEquals( 'ip', $sIp );
	}

	/**
	 * @param string $nLastAccessAfter
	 * @return $this
	 */
	public function filterByLastAccessAfter( $nLastAccessAfter ) {
		return $this->addWhereNewerThan( $nLastAccessAfter, 'last_access_at' );
	}

	/**
	 * @param string $sList
	 * @return $this
	 */
	public function filterByList( $sList ) {
		return $this->addWhereEquals( 'list', $sList );
	}

	/**
	 * @param string $sList
	 * @return ICWP_WPSF_IpsEntryVO[]
	 */
	public function allFromList( $sList ) {
		return $this->reset()
					->filterByList( $sList )
					->query();
	}

	/**
	 * @return ICWP_WPSF_IpsEntryVO[]|stdClass[]
	 */
	public function query() {

		$aData = parent::query();

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_IpsEntryVO( $oAudit );
			}
		}

		return $aData;
	}
}
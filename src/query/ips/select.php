<?php

/**
 * @deprecated v7.0.0
 * Class ICWP_WPSF_Query_Ips_Select
 */
class ICWP_WPSF_Query_Ips_Select extends ICWP_WPSF_Query_BaseSelect {

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
		return [];
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_IpsEntryVO';
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

/**
 * @deprecated
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
		if ( !empty( $sList ) ) {
			$this->addWhereEquals( 'list', $sList );
		}
		return $this;
	}

	/**
	 * @param string $sList
	 * @return ICWP_WPSF_IpsEntryVO[]
	 */
	public function allFromList( $sList ) {
		/** @var ICWP_WPSF_IpsEntryVO[] $aRes */
		$aRes = $this->reset()
					 ->filterByList( $sList )
					 ->query();
		return $aRes;
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_IpsEntryVO';
	}
}
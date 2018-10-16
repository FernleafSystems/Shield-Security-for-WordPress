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
	 * @param string $sList
	 * @return ICWP_WPSF_IpsEntryVO[]
	 */
	public function allFromList( $sList ) {
		return $this->reset()
					->addWhereEquals( 'list', $sList )
					->query();
	}

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @param int    $nLastAccessAfter
	 * @return ICWP_WPSF_IpsEntryVO|null
	 */
	public function getIpFromList( $sIp, $sList, $nLastAccessAfter = 0 ) {
		$oIp = null;
		if ( $this->loadIpService()->isValidIpOrRange( $sIp ) && !empty( $sList ) ) {
			/** @var ICWP_WPSF_IpsEntryVO $oIp */
			$this->reset()
				 ->addWhereEquals( 'ip', $sIp )
				 ->addWhereEquals( 'list', $sList );
			if ( $nLastAccessAfter > 0 ) {
				$this->addWhereNewerThan( $nLastAccessAfter, 'last_access_at' );
			}
			$oIp = $this->first();
		}
		return $oIp;
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
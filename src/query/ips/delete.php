<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Delete', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/delete.php' );

class ICWP_WPSF_Query_Ips_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @return bool
	 */
	public function deleteIpOnList( $sIp, $sList ) {
		$this->reset();
		if ( $this->loadIpService()->isValidIpOrRange( $sIp ) && !empty( $sList ) ) {
			$this->addWhereEquals( 'ip', $sIp )
				 ->addWhereEquals( 'list', $sList );
		}
		return $this->hasWheres() ? $this->query() : false;
	}

	/**
	 * @return ICWP_WPSF_Query_Ips_Count
	 */
	protected function getCounter() {
		require_once( dirname( __FILE__ ).'/count.php' );
		$oCounter = new ICWP_WPSF_Query_Ips_Count();
		return $oCounter->setTable( $this->getTable() );
	}
}
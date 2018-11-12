<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Update', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/update.php' );

class ICWP_WPSF_Query_Scanner_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function filterByHashAndScan( $sHash, $sScan ) {
		$aWhere = array();

		return $this->setUpdateWheres(
			array(

			)
		);
	}

	/**
	 * @param string $sScan
	 * @return $this
	 */
	public function filterByScan(  ) {
		if ( !empty( $sScan ) ) {
			$this->addWhereEquals( 'scan', $sScan );
		}
		return $this;
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Delete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/traffic_entry_base.php' );

class ICWP_WPSF_Query_TrafficEntry_Delete extends ICWP_WPSF_Query_TrafficEntry_Base {

	/**
	 * @param int $nId
	 * @return bool|int
	 */
	public function delete( $nId ) {
		return $this->loadDbProcessor()
					->deleteRowsFromTableWhere( $this->getTable(), array( 'id' => (int)$nId ) );
	}
}
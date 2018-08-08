<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Delete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_delete.php' );

class ICWP_WPSF_Query_TrafficEntry_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @param int $nId
	 * @return bool|int
	 */
	public function deleteById( $nId ) {
		return $this->reset()
					->addWhereEquals( 'id', (int)$nId )
					->query();
	}
}
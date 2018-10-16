<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Count', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/count.php' );

class ICWP_WPSF_Query_Ips_Count extends ICWP_WPSF_Query_BaseCount {

	/**
	 * @param string $sList
	 * @return int
	 */
	public function forList( $sList ) {
		return $this->reset()
					->addWhereEquals( 'list', $sList )
					->query();
	}
}
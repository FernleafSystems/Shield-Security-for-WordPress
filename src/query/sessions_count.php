<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_count.php' );

class ICWP_WPSF_Query_Sessions_Count extends ICWP_WPSF_Query_BaseCount {

	/**
	 * @param string $sUsername
	 * @return int
	 */
	public function forUsername( $sUsername ) {
		return $this->reset()
					->addWhereEquals( 'wp_username', $sUsername )
					->query();
	}
}
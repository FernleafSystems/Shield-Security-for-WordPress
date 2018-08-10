<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

class ICWP_WPSF_Query_TrafficEntry_Count extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @return int
	 */
	public function all() {
		return $this->reset()
					->query();
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "
			SELECT COUNT(*) FROM `%s`
			WHERE %s
			%s
		";
	}

	/**
	 * @return int
	 */
	public function query() {
		return $this->loadDbProcessor()->getVar( $this->buildQuery() );
	}
}
<?php

class ICWP_WPSF_Query_Statistics_Reporting extends ICWP_WPSF_Query_Statistics_Base {

	/**
	 * @return int
	 */
	public function countQuery() {
		return $this->loadDbProcessor()->doSql( $this->buildQuery() );
	}
}
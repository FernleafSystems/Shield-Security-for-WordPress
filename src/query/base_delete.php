<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseDelete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_BaseDelete extends ICWP_WPSF_Query_Base {

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "
			DELETE FROM `%s`
			WHERE %s
			%s
		";
	}

	/**
	 * @return bool
	 */
	public function query() {
		$mResult = $this->loadDbProcessor()->doSql( $this->buildQuery() );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}
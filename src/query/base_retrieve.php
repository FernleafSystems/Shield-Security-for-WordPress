<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseRetrieve', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

class ICWP_WPSF_Query_BaseRetrieve extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @return stdClass[]
	 */
	public function all() {
		return $this->reset()
					->query();
	}

	/**
	 * @param int $nId
	 * @return stdClass
	 */
	public function byId( $nId ) {
		$aItems = $this->reset()
					   ->addWhereEquals( 'id', $nId )
					   ->query();
		return array_shift( $aItems );
	}

	/**
	 * @return stdClass[]
	 */
	public function query() {
		return $this->loadDbProcessor()
					->selectCustom( $this->buildQuery(), OBJECT_K );
	}
}
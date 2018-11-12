<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Insert', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/insert.php' );

class ICWP_WPSF_Query_Scanner_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		if ( !is_string( $oEntry->data ) || strpos( $oEntry->data, '{' ) === false ) {
			$oEntry->data = json_encode( $oEntry->data );
		}
		return parent::insert( $oEntry );
	}
}
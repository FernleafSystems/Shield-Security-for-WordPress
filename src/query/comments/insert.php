<?php

if ( class_exists( 'ICWP_WPSF_Query_Comments_Insert', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/insert.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_Comments_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param ICWP_WPSF_CommentsEntryVO $oToken
	 * @return bool
	 */
	public function insert( $oToken ) {
		if ( !isset( $oToken->ip ) ) {
			$oToken->ip = $this->loadIpService()->getRequestIp();
		}
		return parent::insert( $oToken );
	}
}
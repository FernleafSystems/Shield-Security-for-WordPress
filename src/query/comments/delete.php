<?php

if ( class_exists( 'ICWP_WPSF_Query_Comments_Delete', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/delete.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_Comments_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @param ICWP_WPSF_CommentsEntryVO $oToken
	 * @return bool
	 */
	public function deleteToken( $oToken ) {
		return $this->deleteById( $oToken->getId() );
	}

	/**
	 * @return ICWP_WPSF_Query_Comments_Select
	 */
	protected function getSelector() {
		require_once( dirname( __FILE__ ).'/select.php' );
		$oCounter = new ICWP_WPSF_Query_Comments_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Delete', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/delete.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_PluginNotes_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Select
	 */
	protected function getSelector() {
		require_once( __DIR__.'/select.php' );
		$oCounter = new ICWP_WPSF_Query_PluginNotes_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}
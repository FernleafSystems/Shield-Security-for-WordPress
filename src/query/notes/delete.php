<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Delete', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base_delete.php' );

class ICWP_WPSF_Query_PluginNotes_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_PluginNotes_Count
	 */
	protected function getCounter() {
		require_once( __DIR__.'/count.php' );
		$oCounter = new ICWP_WPSF_Query_PluginNotes_Count();
		return $oCounter->setTable( $this->getTable() );
	}
}
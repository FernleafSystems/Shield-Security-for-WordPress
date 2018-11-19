<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/select.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_PluginNotes_Select extends ICWP_WPSF_Query_BaseSelect {
	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_NoteVO';
	}
}
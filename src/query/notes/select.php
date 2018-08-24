<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base_select.php' );

class ICWP_WPSF_Query_PluginNotes_Select extends ICWP_WPSF_Query_BaseSelect {

	protected function customInit() {
		require_once( __DIR__.'/ICWP_WPSF_NoteVO.php' );
	}

	/**
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
	 */
	public function query() {

		$aData = parent::query();

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_NoteVO( $oAudit );
			}
		}

		return $aData;
	}
}
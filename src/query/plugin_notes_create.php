<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Create', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_PluginNotes_Create extends ICWP_WPSF_Query_Base {

	/**
	 * @param string $sNote
	 * @return bool|int
	 */
	public function create( $sNote ) {

		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		$aNewData = array(
			'wp_username' => ( $oUser instanceof WP_User ) ? $oUser->user_login : 'unknown',
			'note'        => esc_sql( $sNote ),
			'created_at'  => $this->loadDP()->time(),
		);
		$mResult = $this->loadDbProcessor()
						->insertDataIntoTable( $this->getTable(), $aNewData );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}
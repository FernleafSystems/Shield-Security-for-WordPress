<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Insert', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_insert.php' );

class ICWP_WPSF_Query_PluginNotes_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param string $sNote
	 * @return bool|int
	 */
	public function create( $sNote ) {
		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		$aData = array(
			'wp_username' => ( $oUser instanceof WP_User ) ? $oUser->user_login : 'unknown',
			'note'        => esc_sql( $sNote ),
			'created_at'  => $this->loadDP()->time(),
		);
		return $this->setInsertData( $aData )
					->query();
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Insert', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/insert.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_PluginNotes_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param string $sNote
	 * @return bool
	 */
	public function create( $sNote ) {
		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		$aData = array(
			'wp_username' => ( $oUser instanceof WP_User ) ? $oUser->user_login : 'unknown',
			'note'        => esc_sql( $sNote ),
			'created_at'  => $this->loadRequest()->ts(),
		);
		return $this->setInsertData( $aData )->query() === 1;
	}
}
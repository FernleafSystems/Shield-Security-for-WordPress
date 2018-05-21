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
		$oDP = $this->loadDP();

		// Add new session entry
		// set attempts = 1 and then when we know it's a valid login, we zero it.
		// First set any other entries for the given user to be deleted.
		$aNewData = array(
			'note'       => esc_sql( $sNote ),
			'user_id'    => $this->loadWpUsers()->getCurrentWpUserId(),
			'created_at' => $oDP->time(),
		);
		$mResult = $this->loadDbProcessor()
						->insertDataIntoTable( $this->getTable(), $aNewData );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}
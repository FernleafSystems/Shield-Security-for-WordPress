<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Update', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/update.php' );

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;

class ICWP_WPSF_Query_Scanner_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * Also updates last access at
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => $this->loadRequest()->ts() ) );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => 0 ) );
	}
}
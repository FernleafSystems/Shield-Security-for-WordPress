<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Create', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Sessions_Create extends ICWP_WPSF_Query_Base {

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return bool|int
	 */
	public function create( $sUsername, $sSessionId ) {
		$oDP = $this->loadDP();
		$nTimeStamp = $oDP->time();

		// Add new session entry
		// set attempts = 1 and then when we know it's a valid login, we zero it.
		// First set any other entries for the given user to be deleted.
		$aNewData = array(
			'session_id'        => $sSessionId,
			'ip'                => $this->loadIpService()->getRequestIp(), // TODO: SHA1
			'browser'           => md5( $oDP->getUserAgent() ),
			'wp_username'       => $sUsername,
			'logged_in_at'      => $nTimeStamp,
			'created_at'        => $nTimeStamp,
			'last_activity_at'  => $nTimeStamp,
			'last_activity_uri' => $oDP->FetchServer( 'REQUEST_URI' ),
		);
		$mResult = $this->loadDbProcessor()
						->insertDataIntoTable( $this->getTable(), $aNewData );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}
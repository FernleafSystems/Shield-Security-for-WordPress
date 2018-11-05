<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Insert', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/insert.php' );

class ICWP_WPSF_Query_Sessions_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	public function create( $sSessionId, $sUsername ) {
		$oReq = $this->loadRequest();
		$nTimeStamp = $oReq->ts();

		$aData = array(
			'session_id'              => $sSessionId,
			'ip'                      => $this->loadIpService()->getRequestIp(), // TODO: SHA1
			'browser'                 => md5( $oReq->getUserAgent() ),
			'wp_username'             => $sUsername,
			'logged_in_at'            => $nTimeStamp,
			'created_at'              => $nTimeStamp,
			'last_activity_at'        => $nTimeStamp,
			'last_activity_uri'       => $oReq->server( 'REQUEST_URI' ),
			'login_intent_expires_at' => 0,
			'secadmin_at'             => 0,
		);
		return $this->setInsertData( $aData )->query() === 1;
	}
}
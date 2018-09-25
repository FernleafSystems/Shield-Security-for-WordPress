<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Update', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/update.php' );

class ICWP_WPSF_Query_Sessions_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool
	 */
	public function startSecurityAdmin( $oSession ) {
		return $this->updateSession(
			$oSession,
			array( 'secadmin_at' => $this->loadDP()->time() )
		);
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool
	 */
	public function terminateSecurityAdmin( $oSession ) {
		return $this->updateSession(
			$oSession,
			array( 'secadmin_at' => 0 )
		);
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool
	 */
	public function updateLastActivity( $oSession ) {
		$oDP = $this->loadDP();
		return $this->updateSession(
			$oSession,
			array(
				'last_activity_at'  => $oDP->time(),
				'last_activity_uri' => $oDP->server( 'REQUEST_URI' )
			)
		);
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @param int                 $nExpiresAt
	 * @return bool
	 */
	public function updateLoginIntentExpiresAt( $oSession, $nExpiresAt ) {
		return $this->updateSession(
			$oSession,
			array( 'login_intent_expires_at' => (int)$nExpiresAt )
		);
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @param array               $aUpdateData
	 * @return bool
	 */
	public function updateSession( $oSession, $aUpdateData = array() ) {
		$mResult = false;
		if ( !empty( $aUpdateData ) && $oSession instanceof ICWP_WPSF_SessionVO ) {
			$mResult = $this
				->setUpdateData( $aUpdateData )
				->setUpdateWheres(
					array(
						'session_id'  => $oSession->getSessionId(),
						'wp_username' => $oSession->getUsername(),
						'deleted_at'  => 0
					)
				)
				->query();
		}
		return is_numeric( $mResult ) && $mResult === 1;
	}
}
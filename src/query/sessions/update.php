<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Sessions_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * @param $oSession
	 * @return bool
	 */
	public function startSecurityAdmin( $oSession ) {
		return $this->updateSession( $oSession, array( 'secadmin_at' => $this->loadRequest()->ts() ) );
	}

	/**
	 * @param $oSession
	 * @return bool
	 */
	public function terminateSecurityAdmin( $oSession ) {
		return $this->updateSession( $oSession, array( 'secadmin_at' => 0 ) );
	}

	/**
	 * @param $oSession
	 * @return bool
	 */
	public function updateLastActivity( $oSession ) {
		$oR = $this->loadRequest();
		return $this->updateSession(
			$oSession,
			array(
				'last_activity_at'  => $oR->ts(),
				'last_activity_uri' => $oR->server( 'REQUEST_URI' )
			)
		);
	}

	/**
	 * @param     $oSession
	 * @param int $nExpiresAt
	 * @return bool
	 */
	public function updateLoginIntentExpiresAt( $oSession, $nExpiresAt ) {
		return $this->updateSession(
			$oSession,
			array( 'login_intent_expires_at' => (int)$nExpiresAt )
		);
	}

	/**
	 * @param  $oSession
	 * @return bool
	 */
	public function clearLoginIntentCodeEmail( $oSession ) {
		return $this->setLoginIntentCodeEmail( $oSession, '' );
	}

	/**
	 * @param        $oSession
	 * @param string $sCode
	 * @return bool
	 */
	public function setLoginIntentCodeEmail( $oSession, $sCode ) {
		return $this->updateSession( $oSession, array( 'li_code_email' => (string)$sCode ) );
	}

	/**
	 * @param       $oSession
	 * @param array $aUpdateData
	 * @return bool
	 */
	public function updateSession( $oSession, $aUpdateData = array() ) {
		return parent::updateEntry( $oSession, $aUpdateData );
	}
}
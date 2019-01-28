<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function startSecurityAdmin( $oSession ) {
		return $this->updateSession( $oSession, array( 'secadmin_at' => Services::Request()->ts() ) );
	}

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function terminateSecurityAdmin( $oSession ) {
		return $this->updateSession( $oSession, array( 'secadmin_at' => 0 ) );
	}

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function updateLastActivity( $oSession ) {
		$oR = Services::Request();
		return $this->updateSession(
			$oSession,
			array(
				'last_activity_at'  => $oR->ts(),
				'last_activity_uri' => $oR->server( 'REQUEST_URI' )
			)
		);
	}

	/**
	 * @param EntryVO $oSession
	 * @param int     $nExpiresAt
	 * @return bool
	 */
	public function updateLoginIntentExpiresAt( $oSession, $nExpiresAt ) {
		return $this->updateSession(
			$oSession,
			array( 'login_intent_expires_at' => (int)$nExpiresAt )
		);
	}

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function clearLoginIntentCodeEmail( $oSession ) {
		return $this->setLoginIntentCodeEmail( $oSession, '' );
	}

	/**
	 * @param EntryVO $oSession
	 * @param string  $sCode
	 * @return bool
	 */
	public function setLoginIntentCodeEmail( $oSession, $sCode ) {
		return $this->updateSession( $oSession, array( 'li_code_email' => (string)$sCode ) );
	}

	/**
	 * @param EntryVO $oSession
	 * @param array   $aUpdateData
	 * @return bool
	 */
	public function updateSession( $oSession, $aUpdateData = array() ) {
		return parent::updateEntry( $oSession, $aUpdateData );
	}
}
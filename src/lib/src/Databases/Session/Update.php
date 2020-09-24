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
		return $this->updateSession( $oSession, [ 'secadmin_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function terminateSecurityAdmin( $oSession ) {
		return $this->updateSession( $oSession, [ 'secadmin_at' => 0 ] );
	}

	/**
	 * @param EntryVO $oSession
	 * @return bool
	 */
	public function updateLastActivity( $oSession ) {
		$oR = Services::Request();
		return $this->updateSession(
			$oSession,
			[
				'last_activity_at'  => $oR->ts(),
				'last_activity_uri' => $oR->server( 'REQUEST_URI' )
			]
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
			[ 'login_intent_expires_at' => (int)$nExpiresAt ]
		);
	}

	/**
	 * @param EntryVO $oSession
	 * @param array   $aUpdateData
	 * @return bool
	 */
	public function updateSession( $oSession, $aUpdateData = [] ) {
		return parent::updateEntry( $oSession, $aUpdateData );
	}
}
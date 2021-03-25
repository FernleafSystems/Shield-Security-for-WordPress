<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $session
	 * @return bool
	 */
	public function startSecurityAdmin( EntryVO $session ) {
		return $this->updateSession( $session, [ 'secadmin_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $session
	 * @return bool
	 */
	public function terminateSecurityAdmin( EntryVO $session ) {
		return $this->updateSession( $session, [ 'secadmin_at' => 0 ] );
	}

	/**
	 * @param EntryVO $session
	 * @return bool
	 */
	public function updateLastActivity( $session ) {
		$oR = Services::Request();
		return $this->updateSession(
			$session,
			[
				'last_activity_at'  => $oR->ts(),
				'last_activity_uri' => $oR->server( 'REQUEST_URI' )
			]
		);
	}

	/**
	 * @param EntryVO $session
	 * @param int     $nExpiresAt
	 * @return bool
	 */
	public function updateLoginIntentExpiresAt( $session, $nExpiresAt ) {
		return $this->updateSession(
			$session,
			[ 'login_intent_expires_at' => (int)$nExpiresAt ]
		);
	}

	/**
	 * @param EntryVO $session
	 * @param array   $updateData
	 * @return bool
	 */
	public function updateSession( $session, $updateData = [] ) {
		return parent::updateEntry( $session, $updateData );
	}
}
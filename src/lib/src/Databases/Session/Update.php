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
		return true;
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
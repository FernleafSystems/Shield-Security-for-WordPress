<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Update', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Sessions_Update extends ICWP_WPSF_Query_Base {

	/**
	 * @param SessionVO $oSession
	 * @return bool|int
	 */
	public function update( $oSession ) {

		$oDP = $this->loadDP();
		return $this->loadDbProcessor()
					->updateRowsFromTableWhere(
						$this->getTable(),
						array(
							'last_activity_at'  => $oDP->time(),
							'last_activity_uri' => $oDP->FetchServer( 'REQUEST_URI' )
						),
						array(
							'session_id'  => $oSession->getId(),
							'wp_username' => $oSession->getUsername(),
							'deleted_at'  => 0
						)
					);
	}
}
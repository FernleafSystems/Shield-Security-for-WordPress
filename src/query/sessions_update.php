<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Update', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Sessions_Update extends ICWP_WPSF_Query_Base {

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool
	 */
	public function startSecurityAdmin( $oSession ) {
		return $this->querySecurityAdmin( $oSession, true );
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool
	 */
	public function terminateSecurityAdmin( $oSession ) {
		return $this->querySecurityAdmin( $oSession, false );
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool|int
	 */
	public function updateLastActivity( $oSession ) {

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

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @param bool                $bStart - true to start, false to terminate
	 * @return bool
	 */
	private function querySecurityAdmin( $oSession, $bStart ) {
		$mResult = false;
		if ( $oSession instanceof ICWP_WPSF_SessionVO ) {
			$mResult = $this->loadDbProcessor()
							->updateRowsFromTableWhere(
								$this->getTable(),
								array( 'secadmin_at' => $bStart ? $this->loadDP()->time() : 0 ),
								array(
									'session_id'  => $oSession->getId(),
									'wp_username' => $oSession->getUsername(),
									'deleted_at'  => 0
								)
							);
		}
		return ( is_numeric( $mResult ) ) && $mResult == 1;
	}
}
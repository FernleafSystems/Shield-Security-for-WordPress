<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Terminate', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Sessions_Terminate extends ICWP_WPSF_Query_Base {

	/**
	 * @param string $sWpUsername
	 * @return false|int
	 */
	public function forUsername( $sWpUsername ) {
		return $this->query_terminateForUser( $sWpUsername );
	}

	/**
	 * @param SessionVO $oSession
	 * @return false|int
	 */
	public function forUserSession( $oSession ) {
		return $this->query_terminateForUser( $oSession->getUsername(), $oSession->getId() );
	}

	/**
	 * @param string $sWpUsername
	 * @param string $sSessionId
	 * @return false|int
	 */
	protected function query_terminateForUser( $sWpUsername, $sSessionId = '' ) {

		$aWhere = array(
			'wp_username' => $sWpUsername,
			'deleted_at'  => 0
		);
		if ( !empty( $sSessionId ) ) {
			$aWhere[ 'session_id' ] = $sSessionId;
		}
		return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTable(), $aWhere );
	}
}
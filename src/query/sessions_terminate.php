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
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return false|int
	 */
	public function forUserSession( $oSession ) {
		return $this->query_terminateForUser( $oSession->getUsername(), $oSession->getId() );
	}

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginAt( $bOlderThan ) {
		return $this->query_terminateExpired( $bOlderThan, 'logged_in_at' );
	}

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginIdle( $bOlderThan ) {
		return $this->query_terminateExpired( $bOlderThan, 'last_activity_at' );
	}

	/**
	 * @param int    $nOlderThan
	 * @param string $sColumn
	 * @return bool
	 */
	protected function query_terminateExpired( $nOlderThan, $sColumn = 'logged_in_at' ) {

		$sQuery = "
			DELETE FROM `%s`
			WHERE `%s` < %s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			esc_sql( $sColumn ),
			(int)$nOlderThan
		);

		$nCount = $this->loadDbProcessor()
					   ->doSql( $sQuery );

		return is_numeric( $nCount );
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
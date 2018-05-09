<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Retrieve', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_Sessions_Retrieve extends ICWP_WPSF_Query_Base {

	public function __construct() {
		$this->init();
	}

	/**
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function all() {
		return $this->query_retrieveForUserSession();
	}

	/**
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function retrieveForUsername( $sWpUsername ) {
		return $this->query_retrieveForUserSession( $sWpUsername );
	}

	/**
	 * @param string $sWpUsername
	 * @param string $sSessionId
	 * @return ICWP_WPSF_SessionVO|null
	 */
	public function retrieveUserSession( $sWpUsername, $sSessionId ) {
		$aData = $this->query_retrieveForUserSession( $sWpUsername, $sSessionId );
		return ( count( $aData ) == 1 ) ? array_shift( $aData ) : null;
	}

	/**
	 * @param string $sWpUsername
	 * @param string $sSessionId
	 * @return ICWP_WPSF_SessionVO[]
	 */
	protected function query_retrieveForUserSession( $sWpUsername = '', $sSessionId = '' ) {
		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`deleted_at` = 0
				%s
				%s
			ORDER BY `last_activity_at` ASC
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			empty( $sWpUsername ) ? '' : "AND `wp_username` = '".esc_sql( $sWpUsername )."'",
			empty( $sSessionId ) ? '' : "AND `session_id` = '".esc_sql( $sSessionId )."'"
		);

		$aData = $this->loadDbProcessor()
					  ->selectCustom( $sQuery, OBJECT_K );
		foreach ( $aData as $nKey => $oSess ) {
			$aData[ $nKey ] = new ICWP_WPSF_SessionVO( $oSess );
		}
		return $aData;
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_SessionVO.php' );
	}
}
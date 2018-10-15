<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_Sessions_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return ICWP_WPSF_SessionVO[]|stdClass[]
	 */
	public function all() {
		return $this->selectForUserSession();
	}

	/**
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function allForUsername( $sWpUsername ) {
		return $this->addWhereEquals( 'wp_username', $sWpUsername )
					->query();
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO|null
	 */
	public function retrieveUserSession( $sSessionId, $sWpUsername = '' ) {
		$aData = $this->selectForUserSession( $sSessionId, $sWpUsername );
		return ( count( $aData ) == 1 ) ? array_shift( $aData ) : null;
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO[]
	 */
	protected function selectForUserSession( $sSessionId = '', $sWpUsername = '' ) {
		if ( !empty( $sWpUsername ) ) {
			$this->addWhereEquals( 'wp_username', $sWpUsername );
		}
		if ( !empty( $sSessionId ) ) {
			$this->addWhereEquals( 'session_id', $sSessionId );
		}

		return $this->setOrderBy( 'last_activity_at', 'DESC' )
					->query();
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotExpired( $nExpiredBoundary ) {
		return $this->addWhereNewerThan( $nExpiredBoundary, 'logged_in_at' );
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotIdleExpired( $nExpiredBoundary ) {
		return $this->addWhereNewerThan( $nExpiredBoundary, 'last_activity_at' );
	}

	/**
	 * @return ICWP_WPSF_SessionVO[]|stdClass[]
	 */
	public function query() {
		$aData = parent::query();
		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oSess ) {
				$aData[ $nKey ] = new ICWP_WPSF_SessionVO( $oSess );
			}
		}
		return $aData;
	}

	protected function customInit() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_SessionVO.php' );
	}
}
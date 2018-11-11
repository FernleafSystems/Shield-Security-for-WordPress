<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_Sessions_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		return $this->getDistinct_FilterAndSort( 'ip' );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctUsernames() {
		return $this->getDistinct_FilterAndSort( 'wp_username' );
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		if ( $this->loadIpService()->isValidIp( $sIp ) ) {
			$this->addWhereEquals( 'ip', trim( $sIp ) );
		}
		return $this;
	}

	/**
	 * @return ICWP_WPSF_SessionVO[]|stdClass[]
	 */
	public function all() {
		return $this->selectForUserSession();
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
	 * @param int $sUsername
	 * @return $this
	 */
	public function filterByUsername( $sUsername ) {
		return $this->addWhereEquals( 'wp_username', trim( $sUsername ) );
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

		/** @var ICWP_WPSF_SessionVO[] $aRes */
		$aRes = $this->setOrderBy( 'last_activity_at', 'DESC' )->query();
		return $aRes;
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_SessionVO';
	}
}
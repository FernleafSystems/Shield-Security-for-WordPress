<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Select', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base/select.php' );

class ICWP_WPSF_Query_TrafficEntry_Select extends ICWP_WPSF_Query_BaseSelect {

	use ICWP_WPSF_Query_TrafficEntry_Common;

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		$aIps = array_filter( array_map(
			function ( $sIp ) {
				return inet_ntop( $sIp );
			},
			$this->getUnique( 'ip' )
		) );
		asort( $aIps );
		return $aIps;
	}

	/**
	 * @return string[]
	 */
	public function getDistinctCodes() {
		return array_filter( $this->getUnique( 'code' ) );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctUserIds() {
		return array_filter( $this->getUnique( 'uid' ) );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctUsernames() {
		$a = array_filter( array_map(
			function ( $nId ) {
				$oUser = $this->loadWpUsers()->getUserById( $nId );
				return ( $oUser instanceof WP_User ) ? $oUser->user_login : null;
			},
			$this->getDistinctUserIds()
		) );
		asort( $a );
		return $a;
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_TrafficEntryVO';
	}
}
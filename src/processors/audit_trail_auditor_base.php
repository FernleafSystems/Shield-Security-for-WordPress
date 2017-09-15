<?php

class ICWP_WPSF_AuditTrail_Auditor_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var array
	 */
	static protected $aEntries;

	public function add( $sContext, $sEvent, $nCategory, $sMessage = '', $sWpUsername = '' ) {
		$oDp = $this->loadDataProcessor();

		if ( empty( $sWpUsername ) ) {
			$oCurrentUser = $this->loadWpUsers()->getCurrentWpUser();
			$sWpUsername = empty( $oCurrentUser ) ? 'unknown' : $oCurrentUser->get( 'user_login' );
		}

		$aNewEntry = array(
			'ip' => $oDp->getVisitorIpAddress( true ),
			'created_at' => $oDp->GetRequestTime(),
			'wp_username' => $sWpUsername,
			'context' => $sContext,
			'event' => $sEvent,
			'category' => $nCategory,
			'message' => $sMessage
		);
		$aEntries = $this->getAuditTrailEntries();
		$aEntries[] = $aNewEntry;
		self::$aEntries = $aEntries;
	}

	/**
	 * @param boolean $bFlush
	 * @return array
	 */
	public function getAuditTrailEntries( $bFlush = false ) {
		if ( !isset( self::$aEntries ) ) {
			self::$aEntries = array();
		}
		$aEntries = self::$aEntries;
		if ( $bFlush ) {
			self::$aEntries = array();
		}
		return $aEntries;
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Query_Tally_Insert', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/insert.php' );

class ICWP_WPSF_Query_Tally_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param ICWP_WPSF_TallyVO $oTally
	 * @return bool
	 */
	public function insert( $oTally ) {
		$bSuccess = false;
		if ( preg_match( '#[a-z]{1,}\.[a-z]{1,}#i', $oTally->stat_key )
			 && is_numeric( $oTally->tally ) && $oTally->tally > 0 ) {
			$bSuccess = parent::insert( $oTally );
		}
		return $bSuccess;
	}

	/**
	 * @param string sStatKey
	 * @param string $sParent
	 * @param int    $nTally
	 * @return bool
	 */
	public function create( $sStatKey, $nTally, $sParent = '' ) {
		if ( !preg_match( '#[a-z]{1,}\.[a-z]{1,}#i', $sStatKey ) || empty( $nTally )
			 || !is_numeric( $nTally ) || $nTally < 0 ) {
			return false;
		}

		$nTimeStamp = $this->loadDP()->time();
		$aData = array(
			'stat_key'        => $sStatKey,
			'parent_stat_key' => $sParent,
			'tally'           => $nTally,
			'modified_at'     => $nTimeStamp,
			'created_at'      => $nTimeStamp,
		);
		return $this->setInsertData( $aData )->query() === 1;
	}
}
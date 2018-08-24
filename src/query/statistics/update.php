<?php

if ( class_exists( 'ICWP_WPSF_Query_Statistics_Update', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base_update.php' );

class ICWP_WPSF_Query_Statistics_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * @param ICWP_WPSF_StatVO $oStat
	 * @param int              $nAdditional
	 * @return bool
	 */
	public function updateTally( $oStat, $nAdditional ) {
		return $this->updateStat( $oStat, array( 'tally' => $oStat->tally + $nAdditional, ) );
	}

	/**
	 * @param ICWP_WPSF_StatVO $oStat
	 * @param array            $aUpdateData
	 * @return bool
	 */
	public function updateStat( $oStat, $aUpdateData = array() ) {
		$mResult = false;
		if ( !empty( $aUpdateData ) && $oStat instanceof ICWP_WPSF_StatVO ) {
			$mResult = $this
				->setUpdateData( $aUpdateData )
				->setUpdateWheres(
					array(
						'stat_key'        => $oStat->stat_key,
						'parent_stat_key' => $oStat->parent_stat_key,
						'deleted_at'      => 0
					)
				)
				->query();
		}
		return is_numeric( $mResult ) && $mResult === 1;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) :bool {
		$bSuccess = false;
		if ( preg_match( '#[a-z]+\.[a-z]+#i', $oEntry->stat_key )
			 && is_numeric( $oEntry->tally ) && $oEntry->tally > 0 ) {
			$bSuccess = parent::insert( $oEntry );
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

		$nTimeStamp = Services::Request()->ts();
		$aData = [
			'stat_key'        => $sStatKey,
			'parent_stat_key' => $sParent,
			'tally'           => $nTally,
			'modified_at'     => $nTimeStamp,
			'created_at'      => $nTimeStamp,
		];
		return $this->setInsertData( $aData )->query() === 1;
	}
}
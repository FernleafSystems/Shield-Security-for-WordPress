<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseUpdate;

class Update extends BaseUpdate {

	/**
	 * @param EntryVO $oStat
	 * @param int     $nAdditional
	 * @return bool
	 */
	public function incrementTally( $oStat, $nAdditional ) {
		return $this->updateStat( $oStat, array( 'tally' => $oStat->tally + $nAdditional, ) );
	}

	/**
	 * @param EntryVO $oStat
	 * @param array   $aUpdateData
	 * @return bool
	 */
	public function updateStat( $oStat, $aUpdateData = array() ) {
		return parent::updateEntry( $oStat, $aUpdateData );
	}
}
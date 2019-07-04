<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$aTallys = $this->getAllTallys();
		$aTallyTracking = [];
		foreach ( $aTallys as $oTally ) {
			$sKey = preg_replace( '#[^_a-z]#', '', str_replace( '.', '_', $oTally->stat_key ) );
			if ( strpos( $sKey, '_' ) ) {
				$aTallyTracking[ $sKey ] = (int)$oTally->tally;
			}
		}
		$aData[ $this->getMod()->getSlug() ][ 'stats' ] = $aTallyTracking;
		return $aData;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\EntryVO[]
	 */
	protected function getAllTallys() {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally\EntryVO[] $aRes */
		$aRes = $this->getMod()
					 ->getDbHandler()
					 ->getQuerySelector()
					 ->setColumnsToSelect( [ 'stat_key', 'tally' ] )
					 ->query();
		return $aRes;
	}

	/**
	 * @return \ICWP_WPSF_Processor_Statistics_Tally|mixed
	 */
	protected function getTallyProcessor() {
		return $this->getSubPro( 'tally' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'tally' => 'ICWP_WPSF_Processor_Statistics_Tally',
		];
	}
}
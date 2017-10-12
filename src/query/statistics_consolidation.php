<?php

if ( class_exists( 'ICWP_WPSF_Query_Statistics_Consolidation', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'statistics_base.php' );

class ICWP_WPSF_Query_Statistics_Consolidation extends ICWP_WPSF_Query_Statistics_Base {

	/**
	 */
	public function run() {
		$this->consolidateLastMonth();
		$this->consolidateOld();
	}

	protected function consolidateLastMonth() {
		$this->setDateTo( strtotime( 'first second of this month' ) )
			 ->setDateFrom( strtotime( 'first second of last month' ) )
			 ->consolidate();
	}

	protected function consolidateOld() {
		$this->setDateTo( strtotime( 'first second of last month' ) )
			 ->setDateFrom( 0 )
			 ->consolidate();
	}

	protected function consolidate() {
		$aEntries = $this->setSelectDeleted( false )
						 ->runQuery();

		$aIdsToDelete = array();
		$aStatKeyCounter = array();
		foreach ( $aEntries as $oEntry ) {
			if ( !isset( $aStatKeyCounter[ $oEntry->getKey() ] ) ) {
				$aStatKeyCounter[ $oEntry->getKey() ] = 0;
			}
			$aStatKeyCounter[ $oEntry->getKey() ] = +$oEntry->getTally();
			$aIdsToDelete[] = $oEntry->getId();
		}

		// delete all old entries
		$this->deleteAllFromTo();

		// write new cumulative stats
		$oDB = $this->loadDbProcessor();
		foreach ( $aStatKeyCounter as $sStatKey => $nTally ) {
			$oDB->insertDataIntoTable(
				$this->getFeature()->getReportingTableName(),
				array(
					'stat_key'   => $sStatKey,
					'tally'      => 1,
					'created_at' => $this->getDateTo() - 1,
					'deleted_at' => 0,
				)
			);
		}
	}
}
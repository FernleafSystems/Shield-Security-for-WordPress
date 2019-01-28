<?php

class ICWP_WPSF_Query_Statistics_Consolidation extends ICWP_WPSF_Query_Statistics_Base {

	/**
	 */
	public function run() {
		$this->consolidateLastMonth();
		$this->consolidateOld();
	}

	protected function consolidateLastMonth() {
		$this->setDateTo( $this->getFirstOfThisMonth() )
			 ->setDateFrom( $this->getFirstOfLastMonth() )
			 ->consolidate();
	}

	protected function consolidateOld() {
		$this->setDateTo( $this->getFirstOfLastMonth() )
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
				$this->getMod()->getFullReportingTableName(),
				array(
					'stat_key'   => $sStatKey,
					'tally'      => 1,
					'created_at' => $this->getDateTo() - 1,
					'deleted_at' => 0,
				)
			);
		}
	}

	/**
	 * @return int
	 */
	public function getFirstOfThisMonth() {
		$oNow = new \Carbon\Carbon();
		try {
			$oNow->setTimezone( $this->loadWp()->getOption( 'timezone_string' ) );
		}
		catch ( Exception $oE ) {
		}
		return $oNow->day( 1 )
					->hour( 0 )
					->minute( 0 )
					->second( 0 )->timestamp;
	}

	/**
	 * @return int
	 */
	public function getFirstOfLastMonth() {
		$oNow = new \Carbon\Carbon();
		try {
			$oNow->setTimezone( $this->loadWp()->getOption( 'timezone_string' ) );
		}
		catch ( Exception $oE ) {
		}
		return $oNow->day( 1 )
					->hour( 0 )
					->minute( 0 )
					->second( 0 )
					->subMonth( 1 )->timestamp;
	}
}
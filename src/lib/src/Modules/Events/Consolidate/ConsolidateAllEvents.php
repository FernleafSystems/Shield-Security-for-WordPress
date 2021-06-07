<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Consolidate;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConsolidateAllEvents {

	use ModConsumer;

	public function run() {
		foreach ( $this->getAllEvents() as $event ) {
			$this->consolidateEventIntoHourly( $event );
			$this->consolidateEventIntoDaily( $event );
			$this->consolidateEventIntoWeekly( $event );
			$this->consolidateEventIntoMonthly( $event );
			$this->consolidateEventIntoYearly( $event );
		}
	}

	/**
	 * @param $event
	 */
	protected function consolidateEventIntoHourly( $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oDbH = $mod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subHour( 1 )
						 ->startOfHour();

		$nHourCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Hour( $oTime->timestamp )
							 ->filterByEvent( $event )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Hour( $oTime->timestamp )
							 ->sumEvent( $event );
				if ( $nSum > 0 ) {

					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Hour( $oTime->timestamp )
						 ->filterByEvent( $event )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $event;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $oDbH->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nHourCount++;
			$oTime->subHour();
		} while ( $nHourCount < 48 );
	}

	/**
	 * Consolidates each event in Daily sums. Doesn't process events from the previous 48hrs.
	 * Processes event for the 7 days previous to the last 48 hours.
	 * @param $event
	 */
	protected function consolidateEventIntoDaily( $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subDays( 2 )
						->startOfDay();

		$nDayCount = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$recordsCount = $select->filterByBoundary_Day( $time->timestamp )
								   ->filterByEvent( $event )
								   ->count();

			if ( $recordsCount > 1 ) {
				/** @var Events\Select $oSel */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $select->filterByBoundary_Day( $time->timestamp )
							   ->sumEvent( $event );
				if ( $nSum > 0 ) {

					/** @var Events\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Day( $time->timestamp )
							->filterByEvent( $event )
							->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $event;
					$oEntry->count = $nSum;
					$oEntry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $dbh->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nDayCount++;
			$time->subDay();
		} while ( $nDayCount < 13 );
	}

	/**
	 * Consolidates each event in weekly sums. Doesn't process events from the previous 2 whole weeks.
	 * Processes event for the previous 8 weeks.
	 * @param $event
	 */
	protected function consolidateEventIntoWeekly( $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oDbH = $mod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subWeek( 2 )
						 ->startOfWeek();

		$nWeekCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Week( $oTime->timestamp )
							 ->filterByEvent( $event )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Week( $oTime->timestamp )
							 ->sumEvent( $event );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Week( $oTime->timestamp )
						 ->filterByEvent( $event )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $event;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $oDbH->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nWeekCount++;
			$oTime->subWeek();
		} while ( $nWeekCount < 8 );
	}

	/**
	 * Consolidates each event in Daily sums. Doesn't process events from the previous 48hrs.
	 * Processes event for the 7 days previous to the last 48 hours.
	 * @param $event
	 */
	protected function consolidateEventIntoMonthly( $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subMonth( 2 )
						 ->startOfMonth();

		$nMonthCount = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$nRecords = $select->filterByBoundary_Month( $oTime->timestamp )
							   ->filterByEvent( $event )
							   ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $select->filterByBoundary_Month( $oTime->timestamp )
							   ->sumEvent( $event );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $dbh->getQueryDeleter();
					$oDel->filterByBoundary_Month( $oTime->timestamp )
						 ->filterByEvent( $event )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $event;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $dbh->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nMonthCount++;
			$oTime->subMonth();
		} while ( $nMonthCount < 24 );
	}

	/**
	 * @param $event
	 */
	protected function consolidateEventIntoYearly( $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oDbH = $mod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subYear( 2 )
						 ->startOfYear();

		/** @var Events\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oOldest = $oSel->getOldestForEvent( $event );

		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Year( $oTime->timestamp )
							 ->filterByEvent( $event )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Year( $oTime->timestamp )
							 ->sumEvent( $event );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Year( $oTime->timestamp )
						 ->filterByEvent( $event )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $event;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $oDbH->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$oTime->subYear();
		} while ( $oTime->timestamp > $oOldest->created_at );
	}

	/**
	 * @return string[]
	 */
	protected function getAllEvents() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Events\Select $select */
		$select = $mod->getDbHandler_Events()->getQuerySelector();
		return $select->getAllEvents();
	}
}
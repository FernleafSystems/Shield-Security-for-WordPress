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

	protected function consolidateEventIntoHourly( string $event ) {
		$dbh = $this->getCon()->getModule_Events()->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subHour()
						->startOfHour();

		$hourCount = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$nRecords = $select->filterByBoundary_Hour( $time->timestamp )
							   ->filterByEvent( $event )
							   ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $select */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $select->filterByBoundary_Hour( $time->timestamp )
							   ->sumEvent( $event );
				if ( $nSum > 0 ) {

					/** @var Events\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Hour( $time->timestamp )
							->filterByEvent( $event )
							->query();

					$entry = new Events\EntryVO();
					$entry->event = $event;
					$entry->count = $nSum;
					$entry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $inserter */
					$inserter = $dbh->getQueryInserter();
					$inserter->insert( $entry );
				}
			}

			$hourCount++;
			$time->subHour();
		} while ( $hourCount < 48 );
	}

	/**
	 * Consolidates each event in Daily sums. Doesn't process events from the previous 48hrs.
	 * Processes event for the 7 days previous to the last 48 hours.
	 */
	protected function consolidateEventIntoDaily( string $event ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subDays( 2 )
						->startOfDay();

		$count = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$recordsCount = $select->filterByBoundary_Day( $time->timestamp )
								   ->filterByEvent( $event )
								   ->count();

			if ( $recordsCount > 1 ) {
				/** @var Events\Select $select */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$sum = $select->filterByBoundary_Day( $time->timestamp )->sumEvent( $event );
				if ( $sum > 0 ) {

					/** @var Events\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Day( $time->timestamp )
							->filterByEvent( $event )
							->query();

					$entry = new Events\EntryVO();
					$entry->event = $event;
					$entry->count = $sum;
					$entry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $inserter */
					$inserter = $dbh->getQueryInserter();
					$inserter->insert( $entry );
				}
			}

			$count++;
			$time->subDay();
		} while ( $count < 13 );
	}

	/**
	 * Consolidates each event in weekly sums. Doesn't process events from the previous 2 whole weeks.
	 * Processes event for the previous 8 weeks.
	 */
	protected function consolidateEventIntoWeekly( string $event ) {
		$dbh = $this->getCon()->getModule_Events()->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subWeeks( 2 )
						->startOfWeek();

		$count = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$records = $select->filterByBoundary_Week( $time->timestamp )
							  ->filterByEvent( $event )
							  ->count();

			if ( $records > 1 ) {
				/** @var Events\Select $select */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$sum = $select->filterByBoundary_Week( $time->timestamp )
							  ->sumEvent( $event );

				if ( $sum > 0 ) {
					/** @var Events\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Week( $time->timestamp )
							->filterByEvent( $event )
							->query();

					$entry = new Events\EntryVO();
					$entry->event = $event;
					$entry->count = $sum;
					$entry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $inserter */
					$inserter = $dbh->getQueryInserter();
					$inserter->insert( $entry );
				}
			}

			$count++;
			$time->subWeek();
		} while ( $count < 8 );
	}

	protected function consolidateEventIntoMonthly( string $event ) {
		$dbh = $this->getCon()->getModule_Events()->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subMonths( 2 )
						->startOfMonth();

		$count = 0;
		do {
			/** @var Events\Select $select */
			$select = $dbh->getQuerySelector();
			$recordsCount = $select->filterByBoundary_Month( $time->timestamp )
								   ->filterByEvent( $event )
								   ->count();

			if ( $recordsCount > 1 ) {
				/** @var Events\Select $select */
				$select = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$sum = $select->filterByBoundary_Month( $time->timestamp )->sumEvent( $event );

				if ( $sum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $dbh->getQueryDeleter();
					$oDel->filterByBoundary_Month( $time->timestamp )
						 ->filterByEvent( $event )
						 ->query();

					$entry = new Events\EntryVO();
					$entry->event = $event;
					$entry->count = $sum;
					$entry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $inserter */
					$inserter = $dbh->getQueryInserter();
					$inserter->insert( $entry );
				}
			}

			$count++;
			$time->subMonth();
		} while ( $count < 24 );
	}

	protected function consolidateEventIntoYearly( string $event ) {
		$dbh = $this->getCon()->getModule_Events()->getDbHandler_Events();

		$time = Services::Request()
						->carbon()
						->subYear( 2 )
						->startOfYear();

		/** @var Events\Select $selector */
		$selector = $dbh->getQuerySelector();
		$oldest = $selector->getOldestForEvent( $event );

		do {
			/** @var Events\Select $selector */
			$selector = $dbh->getQuerySelector();
			$nRecords = $selector->filterByBoundary_Year( $time->timestamp )
								 ->filterByEvent( $event )
								 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $selector */
				$selector = $dbh->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $selector->filterByBoundary_Year( $time->timestamp )
								 ->sumEvent( $event );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Year( $time->timestamp )
							->filterByEvent( $event )
							->query();

					$entry = new Events\EntryVO();
					$entry->event = $event;
					$entry->count = $nSum;
					$entry->created_at = $time->timestamp + 1;
					/** @var Events\Insert $inserter */
					$inserter = $dbh->getQueryInserter();
					$inserter->insert( $entry );
				}
			}

			$time->subYear();
		} while ( $time->timestamp > $oldest->created_at );
	}

	/**
	 * @return string[]
	 */
	protected function getAllEvents() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Events\Select $select */
		$select = $mod->getDbHandler_Events()->getQuerySelector();
		return array_filter(
			$select->getAllEvents(),
			function ( $event ) {
				return !empty( $event ) && is_string( $event );
			}
		);
	}
}
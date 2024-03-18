<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConsolidateAllEvents {

	use PluginControllerConsumer;

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
		$dbh = self::con()->db_con->events;

		$time = Services::Request()
						->carbon()
						->subHour()
						->startOfHour();

		$hourCount = 0;
		do {
			/** @var EventsDB\Select $select */
			$select = $dbh->getQuerySelector();
			$nRecords = $select->filterByBoundary_Hour( $time->timestamp )
							   ->filterByEvent( $event )
							   ->count();

			if ( $nRecords > 1 ) {
				/** @var EventsDB\Select $select */
				$select = $dbh->getQuerySelector();
				$sum = $select->filterByBoundary_Hour( $time->timestamp )
							  ->sumEvent( $event );
				if ( $sum > 0 ) {

					/** @var EventsDB\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Hour( $time->timestamp )
							->filterByEvent( $event )
							->query();

					/** @var EventsDB\Record $record */
					$record = $dbh->getRecord();
					$record->event = $event;
					$record->count = $sum;
					$record->created_at = $time->timestamp + 1;
					$dbh->getQueryInserter()->insert( $record );
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
		$dbh = self::con()->db_con->events;

		$time = Services::Request()
						->carbon()
						->subDays( 2 )
						->startOfDay();

		$count = 0;
		do {
			/** @var EventsDB\Select $select */
			$select = $dbh->getQuerySelector();
			$recordsCount = $select->filterByBoundary_Day( $time->timestamp )
								   ->filterByEvent( $event )
								   ->count();

			if ( $recordsCount > 1 ) {
				/** @var EventsDB\Select $select */
				$select = $dbh->getQuerySelector();
				$sum = $select->filterByBoundary_Day( $time->timestamp )->sumEvent( $event );
				if ( $sum > 0 ) {

					/** @var EventsDB\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Day( $time->timestamp )
							->filterByEvent( $event )
							->query();

					/** @var EventsDB\Record $record */
					$record = $dbh->getRecord();
					$record->event = $event;
					$record->count = $sum;
					$record->created_at = $time->timestamp + 1;
					$dbh->getQueryInserter()->insert( $record );
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
		$dbh = self::con()->db_con->events;

		$time = Services::Request()
						->carbon()
						->subWeeks( 2 )
						->startOfWeek();

		$count = 0;
		do {
			/** @var EventsDB\Select $select */
			$select = $dbh->getQuerySelector();
			$records = $select->filterByBoundary_Week( $time->timestamp )
							  ->filterByEvent( $event )
							  ->count();

			if ( $records > 1 ) {
				/** @var EventsDB\Select $select */
				$select = $dbh->getQuerySelector();
				$sum = $select->filterByBoundary_Week( $time->timestamp )->sumEvent( $event );

				if ( $sum > 0 ) {
					/** @var EventsDB\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Week( $time->timestamp )
							->filterByEvent( $event )
							->query();

					/** @var EventsDB\Record $record */
					$record = $dbh->getRecord();
					$record->event = $event;
					$record->count = $sum;
					$record->created_at = $time->timestamp + 1;
					$dbh->getQueryInserter()->insert( $record );
				}
			}

			$count++;
			$time->subWeek();
		} while ( $count < 8 );
	}

	protected function consolidateEventIntoMonthly( string $event ) {
		$dbh = self::con()->db_con->events;

		$time = Services::Request()
						->carbon()
						->subMonths( 2 )
						->startOfMonth();

		$count = 0;
		do {
			/** @var EventsDB\Select $select */
			$select = $dbh->getQuerySelector();
			$recordsCount = $select->filterByBoundary_Month( $time->timestamp )
								   ->filterByEvent( $event )
								   ->count();

			if ( $recordsCount > 1 ) {
				/** @var EventsDB\Select $select */
				$select = $dbh->getQuerySelector();
				$sum = $select->filterByBoundary_Month( $time->timestamp )->sumEvent( $event );

				if ( $sum > 0 ) {
					/** @var EventsDB\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Month( $time->timestamp )
							->filterByEvent( $event )
							->query();

					/** @var EventsDB\Record $record */
					$record = $dbh->getRecord();
					$record->event = $event;
					$record->count = $sum;
					$record->created_at = $time->timestamp + 1;
					$dbh->getQueryInserter()->insert( $record );
				}
			}

			$count++;
			$time->subMonth();
		} while ( $count < 24 );
	}

	protected function consolidateEventIntoYearly( string $event ) {
		$dbh = self::con()->db_con->events;

		$time = Services::Request()
						->carbon()
						->subYear()
						->startOfYear();

		/** @var EventsDB\Select $selector */
		$selector = $dbh->getQuerySelector();
		$oldest = $selector->getOldestForEvent( $event );

		do {
			/** @var EventsDB\Select $selector */
			$selector = $dbh->getQuerySelector();
			$records = $selector->filterByBoundary_Year( $time->timestamp )
								->filterByEvent( $event )
								->count();

			if ( $records > 1 ) {
				/** @var EventsDB\Select $selector */
				$selector = $dbh->getQuerySelector();
				$sum = $selector->filterByBoundary_Year( $time->timestamp )->sumEvent( $event );

				if ( $sum > 0 ) {
					/** @var EventsDB\Delete $deleter */
					$deleter = $dbh->getQueryDeleter();
					$deleter->filterByBoundary_Year( $time->timestamp )
							->filterByEvent( $event )
							->query();

					/** @var EventsDB\Record $record */
					$record = $dbh->getRecord();
					$record->event = $event;
					$record->count = $sum;
					$record->created_at = $time->timestamp + 1;
					/** @var EventsDB\Insert $inserter */
					$dbh->getQueryInserter()->insert( $record );
				}
			}

			$time->subYear();
		} while ( $time->timestamp > $oldest->created_at );
	}

	/**
	 * @return string[]
	 */
	protected function getAllEvents() :array {
		/** @var EventsDB\Select $select */
		$select = self::con()->db_con->events->getQuerySelector();
		return \array_filter(
			$select->getAllEvents(),
			function ( $event ) {
				return !empty( $event ) && \is_string( $event );
			}
		);
	}
}
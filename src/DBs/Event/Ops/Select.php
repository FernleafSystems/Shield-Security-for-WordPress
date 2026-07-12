<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Select extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select {

	use Common;

	public function sumEvent( string $event ) :int {
		return $this->sumEvents( [ $event ] );
	}

	/**
	 * @param string[] $events
	 */
	public function sumEvents( array $events ) :int {
		return (int)$this->filterByEvents( $events )
						 ->setColumnsToSelect( [ 'count' ] )
						 ->sum();
	}

	/**
	 * https://stackoverflow.com/questions/6418842/select-multiple-sums-with-mysql-query-and-display-them-in-separate-columns
	 * @param string[] $events
	 */
	public function sumEventsSeparately( array $events ) :array {
		$counts = \array_fill_keys( $events, 0 );
		$rows = $this->filterByEvents( $events )
					 ->setCustomSelect( '`event`, SUM(`count`) AS `event_total`' )
					 ->setGroupBy( 'event' )
					 ->setNoOrderBy()
					 ->setResultsAsVo( false )
					 ->setSelectResultsFormat( ARRAY_A )
					 ->queryWithResult();
		foreach ( \is_array( $rows ) ? $rows : [] as $row ) {
			$event = (string)( $row[ 'event' ] ?? '' );
			if ( \array_key_exists( $event, $counts ) ) {
				$counts[ $event ] = (int)( $row[ 'event_total' ] ?? 0 );
			}
		}
		return $counts;
	}

	public function sumEventsLike( string $event ) :int {
		return (int)$this->addWhereLike( 'event', $event )
						 ->setColumnsToSelect( [ 'count' ] )
						 ->sum();
	}

	/**
	 * @return int[]
	 */
	public function sumAllEvents() :array {
		$allEvents = ( clone $this )->reset()->getAllEvents();
		\natsort( $allEvents );
		return $this->clearWheres()->sumEventsSeparately( \array_values( $allEvents ) );
	}

	public function getLatestForEvent( string $event ) :?Record {
		return $this->filterByEvent( $event )
					->setOrderBy( 'created_at' )
					->setResultsAsVo( true )
					->first();
	}

	public function getOldestForEvent( string $event ) :?Record {
		return $this->filterByEvent( $event )
					->setOrderBy( 'created_at', 'ASC' )
					->setResultsAsVo( true )
					->first();
	}

	/**
	 * @return string[]
	 */
	public function getAllEvents() :array {
		return $this->reset()->getDistinctForColumn( 'event' );
	}

	/**
	 * https://stackoverflow.com/questions/5554075/get-last-distinct-set-of-records
	 * @return Record[] - keys are event names
	 */
	public function getLatestForAllEvents() :array {
		$latest = [];
		$latestIDs = $this->getLatestIdsByTimestamp();
		if ( empty( $latestIDs ) ) {
			return $latest;
		}

		$this->setOrderBy( 'created_at' )
			 ->addWhere( 'id', $latestIDs, 'IN' )
			 ->setResultsAsVo( true );
		$records = $this->queryWithResult();
		foreach ( \is_array( $records ) ? $records : [] as $record ) {
			/** @var Record $record */
			$latest[ $record->event ] = $record;
		}
		return $latest;
	}

	/**
	 * @return int[]
	 */
	private function getLatestIdsByTimestamp() :array {
		$table = $this->getDbH()->getTable();
		$ids = Services::WpDb()->loadWpdb()->get_col(
			"SELECT MAX(`event_row`.`id`)
			FROM `{$table}` AS `event_row`
			INNER JOIN (
				SELECT `event`, MAX(`created_at`) AS `latest_at`
				FROM `{$table}`
				GROUP BY `event`
			) AS `latest`
			ON `latest`.`event` = `event_row`.`event`
				AND `latest`.`latest_at` = `event_row`.`created_at`
			GROUP BY `event_row`.`event`"
		);

		return \array_map( 'intval', \is_array( $ids ) ? $ids : [] );
	}
}

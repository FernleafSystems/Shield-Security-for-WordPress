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

	/**
	 * @param string[] $events
	 * @return array<string,int>
	 */
	public function getLatestTimestampsForEvents( array $events ) :array {
		$events = \array_values( \array_unique( \array_filter( $events, 'is_string' ) ) );
		if ( empty( $events ) ) {
			return [];
		}

		$latest = [];
		$rows = $this->filterByEvents( $events )
					 ->setCustomSelect( '`event`, MAX(`created_at`) AS `latest_at`' )
					 ->setGroupBy( 'event' )
					 ->setNoOrderBy()
					 ->setResultsAsVo( false )
					 ->setSelectResultsFormat( ARRAY_A )
					 ->queryWithResult();
		foreach ( \is_array( $rows ) ? $rows : [] as $row ) {
			$event = (string)( $row[ 'event' ] ?? '' );
			if ( $event !== '' && \in_array( $event, $events, true ) ) {
				$latest[ $event ] = (int)( $row[ 'latest_at' ] ?? 0 );
			}
		}

		return $latest;
	}

	/**
	 * Each event's total in each window, from one grouped query. The windows are consecutive, so each row goes in the
	 * last window whose start it has passed, which keeps days of any length, such as summer-time changes. The CASE is
	 * plain SQL, so it runs on SQLite as on MySQL.
	 * @param string[] $events
	 * @param int[]    $windowStarts consecutive window starts, ascending
	 * @return array<int,array<string,int>> window index => event => total, for the windows and events with rows
	 * @throws \RuntimeException when the query fails, so no one reads a failure as no events
	 */
	public function sumEventsPerWindow( array $events, array $windowStarts, int $endAt ) :array {
		$events = \array_values( \array_unique( \array_filter( $events, 'is_string' ) ) );
		$windowStarts = \array_values( \array_map( 'intval', $windowStarts ) );
		if ( empty( $events ) || empty( $windowStarts ) ) {
			return [];
		}

		$whens = [];
		foreach ( \array_slice( $windowStarts, 1 ) as $index => $nextStart ) {
			$whens[] = "WHEN `created_at` < {$nextStart} THEN {$index}";
		}
		// A CASE needs a WHEN, so a single window is just window 0.
		$windowCase = empty( $whens ) ? '0' : 'CASE '.\implode( ' ', $whens ).' ELSE '.\count( $whens ).' END';

		$wpdb = Services::WpDb()->loadWpdb();
		$table = $this->getDbH()->getTable();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `event`, {$windowCase} AS `window_index`, SUM(`count`) AS `event_total`
			FROM `{$table}`
			WHERE `created_at` >= %d AND `created_at` <= %d
				AND `event` IN (".\implode( ',', \array_fill( 0, \count( $events ), '%s' ) ).")
			GROUP BY `event`, `window_index`",
			$windowStarts[ 0 ],
			$endAt,
			...$events
		), ARRAY_A );
		if ( !\is_array( $rows ) || $wpdb->last_error !== '' ) {
			throw new \RuntimeException( 'Failed to query the event totals per window.' );
		}

		$totals = [];
		foreach ( $rows as $row ) {
			$totals[ (int)$row[ 'window_index' ] ][ (string)$row[ 'event' ] ] = (int)$row[ 'event_total' ];
		}
		return $totals;
	}

	/**
	 * Null when the table holds no events.
	 * @throws \RuntimeException when the query fails, so no one reads a failure as an empty table
	 */
	public function getOldestCreatedAt() :?int {
		$wpdb = Services::WpDb()->loadWpdb();
		$oldest = $wpdb->get_var( "SELECT MIN(`created_at`) FROM `{$this->getDbH()->getTable()}`" );
		if ( $wpdb->last_error !== '' ) {
			throw new \RuntimeException( 'Failed to query the oldest event timestamp.' );
		}

		return $oldest === null ? null : (int)$oldest;
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

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
		/** @var Record $event */
		foreach ( $this->filterByEvents( $events )->queryWithResult() as $event ) {
			$counts[ $event->event ] += $event->count;
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
		$sums = [];

		$allEvents = ( clone $this )->reset()->getAllEvents();

		\natsort( $allEvents );
		foreach ( $allEvents as $event ) {
			$sums[ $event ] = $this->clearWheres()->sumEvent( $event );
		}
		return $sums;
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
		$this->setGroupBy( 'event' )
			 ->setOrderBy( 'created_at' )
			 ->addWhere( 'id', $this->getMaxIds(), 'IN' )
			 ->setResultsAsVo( true );
		foreach ( $this->queryWithResult() as $record ) {
			/** @var Record $record */
			$latest[ $record->event ] = $record;
		}
		return $latest;
	}

	/**
	 * @return int[]
	 */
	private function getMaxIds() :array {
		return \array_map(
			function ( $id ) {
				return (int)$id[ 'MAX(id)' ];
			},
			$this->setCustomSelect( 'MAX(id)' )
				 ->setGroupBy( 'event' )
				 ->setResultsAsVo( false )
				 ->setSelectResultsFormat( ARRAY_A )
				 ->queryWithResult()
		);
	}
}
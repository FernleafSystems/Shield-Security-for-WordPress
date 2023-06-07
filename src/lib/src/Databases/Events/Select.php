<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	/**
	 * @param string $event
	 */
	public function sumEvent( $event ) :int {
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

		natsort( $allEvents );
		foreach ( $allEvents as $event ) {
			$sums[ $event ] = (int)$this->clearWheres()->sumEvent( $event );
		}
		return $sums;
	}

	/**
	 * @return EntryVO|null
	 */
	public function getLatestForEvent( string $event ) {
		return $this->filterByEvent( $event )
					->setOrderBy( 'created_at' )
					->setResultsAsVo( true )
					->first();
	}

	/**
	 * @param string $event
	 * @return EntryVO|null
	 */
	public function getOldestForEvent( $event ) {
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
	 * @return EntryVO[] - keys are event names
	 */
	public function getLatestForAllEvents() :array {
		$latest = [];
		$this->setGroupBy( 'event' )
			 ->setOrderBy( 'created_at' )
			 ->addWhere( 'id', $this->getMaxIds(), 'IN' )
			 ->setResultsAsVo( true );
		foreach ( $this->query() as $entry ) {
			/** @var EntryVO $entry */
			$latest[ $entry->event ] = $entry;
		}
		return $latest;
	}

	/**
	 * @return int[]
	 */
	private function getMaxIds() {
		return array_map(
			function ( $id ) {
				return (int)$id[ 'MAX(id)' ];
			},
			$this->setCustomSelect( 'MAX(id)' )
				 ->setGroupBy( 'event' )
				 ->setResultsAsVo( false )
				 ->setSelectResultsFormat( ARRAY_A )
				 ->query()
		);
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	/**
	 * @param string $event
	 * @return int
	 */
	public function sumEvent( $event ) :int {
		return $this->sumEvents( [ $event ] );
	}

	/**
	 * @param string[] $events
	 * @return int
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
	public function sumAllEvents() {
		$sums = [];

		$allEvents = ( clone $this )->reset()->getAllEvents();

		natsort( $allEvents );
		foreach ( $allEvents as $event ) {
			$sums[ $event ] = (int)$this->clearWheres()->sumEvent( $event );
		}
		return $sums;
	}

	/**
	 * @param string $event
	 * @return EntryVO|null
	 */
	public function getLatestForEvent( string $event ) {
		return $this->filterByEvent( $event )
					->setOrderBy( 'created_at', 'DESC' )
					->setResultsAsVo( true )
					->first();
	}

	/**
	 * @param string $sEvent
	 * @return EntryVO|null
	 */
	public function getOldestForEvent( $sEvent ) {
		return $this->filterByEvent( $sEvent )
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
	public function getLatestForAllEvents() {
		$latest = [];
		$this->setGroupBy( 'event' )
			 ->setOrderBy( 'created_at', 'DESC' )
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
		$aIds = $this->setCustomSelect( 'MAX(id)' )
					 ->setGroupBy( 'event' )
					 ->setResultsAsVo( false )
					 ->setSelectResultsFormat( ARRAY_A )
					 ->query();
		return array_map(
			function ( $aId ) {
				return (int)$aId[ 'MAX(id)' ];
			},
			$aIds
		);
	}

	/**
	 * @param int $nGreaterThan
	 * @return $this
	 */
	public function filterByCountGreaterThan( $nGreaterThan ) {
		return $this->addWhere( 'count', (int)$nGreaterThan, '>' );
	}
}
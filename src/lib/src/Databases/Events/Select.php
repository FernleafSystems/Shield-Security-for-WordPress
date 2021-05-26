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

	/**
	 * @param string $sEvent
	 * @return int
	 */
	public function sumEventsLike( $sEvent ) {
		return (int)$this->addWhereLike( 'event', $sEvent )
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
			$sums[ $event ] = $this->clearWheres()->sumEvent( $event );
		}
		return $sums;
	}

	/**
	 * @param string $sEvent
	 * @return EntryVO|null
	 */
	public function getLatestForEvent( $sEvent ) {
		return $this->filterByEvent( $sEvent )
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
		$aKeyedLatest = [];
		$this->setGroupBy( 'event' )
			 ->setOrderBy( 'created_at', 'DESC' )
			 ->addWhere( 'id', $this->getMaxIds(), 'IN' )
			 ->setResultsAsVo( true );
		foreach ( $this->query() as $oEntry ) {
			/** @var EntryVO $oEntry */
			$aKeyedLatest[ $oEntry->event ] = $oEntry;
		}
		return $aKeyedLatest;
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
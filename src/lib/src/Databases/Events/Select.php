<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	/**
	 * @param string $sEvent
	 * @return int
	 */
	public function sumEvent( $sEvent ) {
		return $this->sumEvents( [ $sEvent ] );
	}

	/**
	 * @param string[] $aEvents
	 * @return int
	 */
	public function sumEvents( $aEvents ) {
		return (int)$this->filterByEvents( $aEvents )
						 ->setColumnsToSelect( [ 'count' ] )
						 ->sum();
	}

	/**
	 * @return int[]
	 */
	public function sumAllEvents() {
		$aSums = [];

		$oNewMe = clone $this;
		$aAllEvents = $oNewMe->reset()->getAllEvents();

		natsort( $aAllEvents );
		foreach ( $aAllEvents as $sEvent ) {
			$aSums[ $sEvent ] = $this->clearWheres()->sumEvent( $sEvent );
		}
		return $aSums;
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
	 * @return string[]
	 */
	public function getAllEvents() {
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

	/**
	 * @param string $sEvent
	 * @return $this
	 */
	public function filterByEvent( $sEvent ) {
		return $this->filterByEvents( [ $sEvent ] );
	}

	/**
	 * @param string[] $aEvents
	 * @return $this
	 */
	public function filterByEvents( $aEvents ) {
		return $this->addWhereIn( 'event', $aEvents );
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Consolidate;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConsolidateAllEvents {

	use ModConsumer;

	public function run() {
		foreach ( $this->getAllEvents() as $sEvent ) {
			$this->consolidateEventIntoHourly( $sEvent );
			$this->consolidateEventIntoDaily( $sEvent );
			$this->consolidateEventIntoWeekly( $sEvent );
			$this->consolidateEventIntoMonthly( $sEvent );
			$this->consolidateEventIntoYearly( $sEvent );
		}
	}

	/**
	 * @param $sEvent
	 */
	protected function consolidateEventIntoHourly( $sEvent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subHour( 1 )
						 ->startOfHour();

		$nHourCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Hour( $oTime->timestamp )
							 ->filterByEvent( $sEvent )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Hour( $oTime->timestamp )
							 ->sumEvent( $sEvent );
				if ( $nSum > 0 ) {

					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Hour( $oTime->timestamp )
						 ->filterByEvent( $sEvent )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $sEvent;
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
	 * @param $sEvent
	 */
	protected function consolidateEventIntoDaily( $sEvent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subDays( 2 )
						 ->startOfDay();

		$nDayCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Day( $oTime->timestamp )
							 ->filterByEvent( $sEvent )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Day( $oTime->timestamp )
							 ->sumEvent( $sEvent );
				if ( $nSum > 0 ) {

					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Day( $oTime->timestamp )
						 ->filterByEvent( $sEvent )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $sEvent;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $oDbH->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nDayCount++;
			$oTime->subDay();
		} while ( $nDayCount < 13 );
	}

	/**
	 * Consolidates each event in weekly sums. Doesn't process events from the previous 2 whole weeks.
	 * Processes event for the previous 8 weeks.
	 * @param $sEvent
	 */
	protected function consolidateEventIntoWeekly( $sEvent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subWeek( 2 )
						 ->startOfWeek();

		$nWeekCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Week( $oTime->timestamp )
							 ->filterByEvent( $sEvent )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Week( $oTime->timestamp )
							 ->sumEvent( $sEvent );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Week( $oTime->timestamp )
						 ->filterByEvent( $sEvent )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $sEvent;
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
	 * @param $sEvent
	 */
	protected function consolidateEventIntoMonthly( $sEvent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subMonth( 2 )
						 ->startOfMonth();

		$nMonthCount = 0;
		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Month( $oTime->timestamp )
							 ->filterByEvent( $sEvent )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Month( $oTime->timestamp )
							 ->sumEvent( $sEvent );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Month( $oTime->timestamp )
						 ->filterByEvent( $sEvent )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $sEvent;
					$oEntry->count = $nSum;
					$oEntry->created_at = $oTime->timestamp + 1;
					/** @var Events\Insert $oQI */
					$oQI = $oDbH->getQueryInserter();
					$oQI->insert( $oEntry );
				}
			}

			$nMonthCount++;
			$oTime->subMonth();
		} while ( $nMonthCount < 24 );
	}

	/**
	 * @param $sEvent
	 */
	protected function consolidateEventIntoYearly( $sEvent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_Events();

		$oTime = Services::Request()
						 ->carbon()
						 ->subYear( 2 )
						 ->startOfYear();

		/** @var Events\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oOldest = $oSel->getOldestForEvent( $sEvent );

		do {
			/** @var Events\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			$nRecords = $oSel->filterByBoundary_Year( $oTime->timestamp )
							 ->filterByEvent( $sEvent )
							 ->count();

			if ( $nRecords > 1 ) {
				/** @var Events\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				/** @var Events\EntryVO[] $aRecords */
				$nSum = $oSel->filterByBoundary_Year( $oTime->timestamp )
							 ->sumEvent( $sEvent );

				if ( $nSum > 0 ) {
					/** @var Events\Delete $oDel */
					$oDel = $oDbH->getQueryDeleter();
					$oDel->filterByBoundary_Year( $oTime->timestamp )
						 ->filterByEvent( $sEvent )
						 ->query();

					$oEntry = new Events\EntryVO();
					$oEntry->event = $sEvent;
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
	protected function getAllEvents() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var Events\Select $oSel */
		$oSel = $oMod->getDbHandler_Events()->getQuerySelector();
		return $oSel->getAllEvents();
	}
}
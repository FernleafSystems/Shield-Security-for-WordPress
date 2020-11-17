<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVO {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * @param string $reportType
	 */
	public function __construct( string $reportType ) {
		$this->rep = new ReportVO();
		$this->rep->type = $reportType;
	}

	/**
	 * @return ReportVO
	 * @throws \Exception
	 */
	public function create() {
		$this->setReportInterval()
			 ->setPreviousReport()
			 ->setIntervalBoundaries()
			 ->setReportId();
		return $this->rep;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	private function setReportInterval() {
		/** @var Reporting\Options $opts */
		$opts = $this->getOptions();

		switch ( $this->rep->type ) {
			case Reports\Handler::TYPE_ALERT:
				$this->rep->interval = $opts->getFrequencyAlert();
				break;
			case Reports\Handler::TYPE_INFO:
				$this->rep->interval = $opts->getFrequencyInfo();
				break;
			default:
				throw new \Exception( 'Not a supported report type: '.$this->rep->type );
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	private function setPreviousReport() {
		/** @var Reporting\ModCon $mod */
		$mod = $this->getMod();
		/** @var Reports\Select $sel */
		$sel = $mod->getDbHandler_Reports()->getQuerySelector();
		$this->rep->previous = $sel->filterByType( $this->rep->type )
								   ->filterByFrequency( $this->rep->interval )
								   ->setOrderBy( 'sent_at', 'DESC' )
								   ->first();
		return $this;
	}

	/**
	 * Here we test whether the report time boundary overlaps with the boundaries of the previous report.
	 * If it does overlap, we're creating a duplicate report.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function setIntervalBoundaries() {

		$carbon = Services::Request()->carbon( true );
		$nAddition = -1; // the previous hour, day, week, month

		switch ( $this->rep->interval ) {
//			case 'realtime':
//				break;
			case 'no_time': // TODO
				$start = 0;
				$end = $carbon->timestamp;
				break;
			case 'hourly':
				$carbon->addHours( $nAddition );
				$start = $carbon->startOfHour()->timestamp;
				$end = $carbon->endOfHour()->timestamp;
				break;
			case 'daily':
				$carbon->addDays( $nAddition );
				$start = $carbon->startOfDay()->timestamp;
				$end = $carbon->endOfDay()->timestamp;
				break;
			case 'weekly':
				$carbon->addWeeks( $nAddition );
				$start = $carbon->startOfWeek()->timestamp;
				$end = $carbon->endOfWeek()->timestamp;
				break;
			case 'monthly':
				$carbon->addMonths( $nAddition );
				$start = $carbon->startOfMonth()->timestamp;
				$end = $carbon->endOfMonth()->timestamp;
				break;
			case 'yearly':
				$carbon->addYears( $nAddition );
				$start = $carbon->startOfYear()->timestamp;
				$end = $carbon->endOfYear()->timestamp;
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
		}

		if ( $this->rep->previous instanceof Reports\EntryVO
			 && $end <= $this->rep->previous->interval_end_at ) {
			throw new \Exception( 'Attempting to create a duplicate report based on interval.' );
		}

		$this->rep->interval_start_at = $start;
		$this->rep->interval_end_at = $end;

		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	private function setReportId() {
		/** @var Reporting\ModCon $mod */
		$mod = $this->getMod();
		/** @var Reports\Select $oSel */
		$oSel = $mod->getDbHandler_Reports()->getQuerySelector();
		$nPrevID = $oSel->getLastReportId();
		$this->rep->rid = is_numeric( $nPrevID ) ? $nPrevID + 1 : 1;
		return $this;
	}

	/**
	 * TODO
	 * @return bool
	 */
	private function isOnDemandReport() {
		return !Services::WpGeneral()->isCron();
	}
}

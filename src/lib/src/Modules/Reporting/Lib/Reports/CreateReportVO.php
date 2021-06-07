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

	public function __construct( string $reportType ) {
		$this->rep = new ReportVO();
		$this->rep->type = $reportType;
	}

	/**
	 * @return ReportVO
	 * @throws \Exception
	 */
	public function create() :ReportVO {
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
		$req = Services::Request();

		$intervalToReport = $req->carbon( true );
		$currentIntervalStart = $req->carbon( true );

		switch ( $this->rep->interval ) {
//			case 'realtime':
//				break;
			case 'lifetime': // TODO
				$start = 0;
				$end = $intervalToReport->timestamp;
				break;
			case 'hourly':
				$currentIntervalStart->startOfHour();
				$intervalToReport->subHour();
				$start = $intervalToReport->startOfHour()->timestamp;
				$end = $intervalToReport->endOfHour()->timestamp;
				break;

			case 'daily':
				$currentIntervalStart->startOfDay();
				$intervalToReport->subDay();
				$start = $intervalToReport->startOfDay()->timestamp;
				$end = $intervalToReport->endOfDay()->timestamp;
				break;

			case 'weekly':
				$currentIntervalStart->startOfWeek();
				$intervalToReport->subWeek();
				$start = $intervalToReport->startOfWeek()->timestamp;
				$end = $intervalToReport->endOfWeek()->timestamp;
				break;

			case 'monthly':
				$currentIntervalStart->startOfMonth();
				$intervalToReport->day( 15 )->subMonth();
				$start = $intervalToReport->startOfMonth()->timestamp;
				$end = $intervalToReport->endOfMonth()->timestamp;
				break;

			case 'yearly':
				$currentIntervalStart->startOfYear();
				$intervalToReport->subYear();
				$start = $intervalToReport->startOfYear()->timestamp;
				$end = $intervalToReport->endOfYear()->timestamp;
				break;

			default:
				throw new \Exception( 'Not a supported frequency' );
		}

		if ( $this->rep->previous instanceof Reports\EntryVO && $end <= $this->rep->previous->interval_end_at ) {
			throw new \Exception( 'Attempting to create a duplicate report based on interval.' );
		}

		if ( $end > $currentIntervalStart->timestamp ) { // sanity check
			throw new \Exception( 'Attempting to create for an interval greater than the current interval.' );
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
		/** @var Reports\Select $select */
		$select = $mod->getDbHandler_Reports()->getQuerySelector();
		$prevID = $select->getLastReportId();
		$this->rep->rid = is_numeric( $prevID ) ? $prevID + 1 : 1;
		return $this;
	}
}

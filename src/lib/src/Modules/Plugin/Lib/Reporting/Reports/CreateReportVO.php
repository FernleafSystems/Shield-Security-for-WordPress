<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\{
	DB\Report\Ops as ReportsDB,
	Lib\Reporting\Constants,
	ModCon,
	Options};
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVO {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * @throws Exceptions\AttemptingToCreateDuplicateReportException
	 * @throws Exceptions\AttemptingToCreateDisabledReportException
	 * @throws \Exception
	 */
	public function create( string $reportType ) :ReportVO {
		$this->rep = new ReportVO();
		$this->rep->type = $reportType;

		$this->setReportInterval()
			 ->setPreviousReport()
			 ->setIntervalBoundaries();
		return $this->rep;
	}

	private function setReportInterval() :self {
		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $this->rep->type ) {
			case Constants::REPORT_TYPE_ALERT:
				$this->rep->interval = $opts->getReportFrequencyAlert();
				break;
			case Constants::REPORT_TYPE_INFO:
			default:
				$this->rep->interval = $opts->getReportFrequencyInfo();
				break;
		}
		return $this;
	}

	private function setPreviousReport() :self {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ReportsDB\Select $sel */
		$sel = $mod->getDbH_ReportLogs()->getQuerySelector();
		$this->rep->previous = $sel->filterByType( $this->rep->type )
								   ->filterByInterval( $this->rep->interval )
								   ->setOrderBy( 'created_at' )
								   ->first();
		return $this;
	}

	/**
	 * @throws Exceptions\AttemptingToCreateDuplicateReportException
	 * @throws Exceptions\AttemptingToCreateDisabledReportException
	 * @throws \Exception
	 */
	private function setIntervalBoundaries() :self {
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

			case 'disabled':
			default:
				throw new Exceptions\AttemptingToCreateDisabledReportException( 'Attempting to create a report for a disabled interval.' );
		}

		if ( $this->rep->previous instanceof ReportsDB\Record && $end <= $this->rep->previous->interval_end_at ) {
			throw new Exceptions\AttemptingToCreateDuplicateReportException( 'Attempting to create a duplicate report based on interval.' );
		}

		if ( $end > $currentIntervalStart->timestamp ) { // sanity check
			throw new \Exception( 'Attempting to create for an interval greater than the current interval.' );
		}

		$this->rep->interval_start_at = $start;
		$this->rep->interval_end_at = $end;

		return $this;
	}
}

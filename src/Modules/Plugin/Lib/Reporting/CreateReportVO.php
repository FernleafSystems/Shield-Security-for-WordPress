<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVO {

	use PluginControllerConsumer;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * @throws Exceptions\DuplicateReportException
	 * @throws Exceptions\ReportTypeDisabledException
	 */
	public function create( string $reportType ) :ReportVO {
		$this->rep = new ReportVO();
		$this->rep->type = $reportType;

		$this->setReportInterval()
			 ->setPreviousReport()
			 ->setReportAreas()
			 ->setIntervalBoundaries();

		$this->rep->title = sprintf( '%s :: %s :: %s',
			self::con()->comps->reports->getReportTypeName( $reportType ),
			\ucfirst( $this->rep->interval ),
			__( 'Auto-Generated', 'wp-simple-firewall' )
		);

		return $this->rep;
	}

	private function setReportAreas() :self {
		switch ( $this->rep->type ) {
			case Constants::REPORT_TYPE_ALERT:
				$this->rep->areas = [
					Constants::REPORT_AREA_SCANS => [
						'scan_results_new',
						'scan_results_current',
						'scan_repairs',
					],
				];
				break;
			case Constants::REPORT_TYPE_INFO:
			default:
				$this->rep->areas = self::con()->comps->reports->getReportAreas( true );
				break;
		}
		return $this;
	}

	private function setReportInterval() :self {
		switch ( $this->rep->type ) {
			case Constants::REPORT_TYPE_ALERT:
				$this->rep->interval = self::con()->comps->reports->getReportFrequencyAlert();
				break;
			case Constants::REPORT_TYPE_INFO:
			default:
				$this->rep->interval = self::con()->comps->reports->getReportFrequencyInfo();
				break;
		}
		return $this;
	}

	private function setPreviousReport() :self {
		/** @var ReportsDB\Select $sel */
		$sel = self::con()->db_con->reports->getQuerySelector();
		$this->rep->previous = $sel->filterByType( $this->rep->type )
								   ->filterByInterval( $this->rep->interval )
								   ->setOrderBy( 'created_at' )
								   ->first();
		return $this;
	}

	/**
	 * @throws Exceptions\DuplicateReportException
	 * @throws Exceptions\ReportTypeDisabledException
	 * @throws \Exception
	 */
	private function setIntervalBoundaries() :self {
		$req = Services::Request();

		$intervalToReport = $req->carbon( true );
		$currentIntervalStart = $req->carbon( true );

		switch ( $this->rep->interval ) {
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
				throw new Exceptions\ReportTypeDisabledException( 'Attempting to create a report for a disabled interval.' );
		}

		if ( $this->rep->previous instanceof ReportsDB\Record && $end <= $this->rep->previous->interval_end_at ) {
			throw new Exceptions\DuplicateReportException( 'Attempting to create a duplicate report based on interval.' );
		}

		if ( $end > $currentIntervalStart->timestamp ) { // sanity check
			throw new \Exception( 'Attempting to create for an interval greater than the current interval.' );
		}

		$this->rep->start_at = $start;
		$this->rep->end_at = $end;

		return $this;
	}
}

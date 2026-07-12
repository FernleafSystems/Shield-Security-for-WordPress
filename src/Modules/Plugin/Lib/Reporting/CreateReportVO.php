<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVO {

	use PluginControllerConsumer;

	private ReportVO $rep;

	/**
	 * @throws Exceptions\DuplicateReportException
	 * @throws Exceptions\ReportTypeDisabledException
	 */
	public function create( string $reportType ) :ReportVO {
		$this->rep = new ReportVO();
		$this->rep->type = $reportType;

		$this->rep->interval = $this->determineReportInterval( $reportType );
		$previous = $this->lookupPreviousReport( $reportType, $this->rep->interval );
		$this->rep->areas = $this->determineReportAreas( $reportType );
		$this->setIntervalBoundaries( $previous );
		$this->rep->title = $this->buildReportTitle( $reportType, $this->rep->interval );

		return $this->rep;
	}

	protected function determineReportAreas( string $reportType ) :array {
		return $reportType === Constants::REPORT_TYPE_ALERT
			? [
				Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
			]
			: self::con()->comps->reports->getReportAreas( true );
	}

	protected function determineReportInterval( string $reportType ) :string {
		switch ( $reportType ) {
			case Constants::REPORT_TYPE_ALERT:
				return self::con()->comps->reports->getReportFrequencyAlert();
			case Constants::REPORT_TYPE_INFO:
			default:
				return self::con()->comps->reports->getReportFrequencyInfo();
		}
	}

	protected function lookupPreviousReport( string $reportType, string $interval ) :?ReportsDB\Record {
		/** @var ReportsDB\Select $sel */
		$sel = self::con()->db_con->reports->getQuerySelector();
		$previous = $sel->filterByType( $reportType )
						->filterByInterval( $interval )
						->setOrderBy( 'created_at' )
						->first();

		return $previous instanceof ReportsDB\Record ? $previous : null;
	}

	/**
	 * @throws Exceptions\DuplicateReportException
	 * @throws Exceptions\ReportTypeDisabledException
	 * @throws \Exception
	 */
	private function setIntervalBoundaries( ?ReportsDB\Record $previous ) :self {
		$interval = $this->rep->interval;
		$resolver = $this->buildIntervalWindowResolver();

		if ( !$resolver->isSupportedScheduledInterval( $interval ) ) {
			throw new Exceptions\ReportTypeDisabledException( 'Attempting to create a report for a disabled interval.' );
		}

		$window = $resolver->resolveCompletedWindow( $interval, $this->currentRequestCarbon() );

		if ( $previous !== null && $window->end_at <= $previous->interval_end_at ) {
			throw new Exceptions\DuplicateReportException( 'Attempting to create a duplicate report based on interval.' );
		}

		if ( $window->end_at >= $this->currentRequestCarbon()->timestamp ) { // sanity check
			throw new \Exception( 'Attempting to create for an interval greater than the current interval.' );
		}

		$this->rep->start_at = $window->start_at;
		$this->rep->end_at = $window->end_at;
		$previousWindow = $resolver->resolvePreviousMatchingWindow( $window, $interval );
		$this->rep->previous_start_at = $previousWindow->start_at;
		$this->rep->previous_end_at = $previousWindow->end_at;

		return $this;
	}

	protected function buildReportTitle( string $reportType, string $interval ) :string {
		return sprintf( '%s :: %s :: %s',
			self::con()->comps->reports->getReportTypeName( $reportType ),
			\ucfirst( $interval ),
			__( 'Auto-Generated', 'wp-simple-firewall' )
		);
	}

	protected function buildIntervalWindowResolver() :ReportIntervalWindowResolver {
		return new ReportIntervalWindowResolver();
	}

	protected function currentRequestCarbon() :Carbon {
		return Carbon::createFromTimestamp( Services::Request()->ts(), \wp_timezone() );
	}
}

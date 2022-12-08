<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\{
	ReportsBuilderAlerts,
	ReportsBuilderInfo
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Constants;
use FernleafSystems\Wordpress\Services\Services;

class StandardReportBuilder {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	protected $rep;

	/**
	 * @throws \Exception
	 */
	public function build( ReportVO $report ) {
		$this->rep = $report;
		if ( $this->isReadyToSend() ) {
			$gatheredReports = $this->gather();
			if ( !empty( $gatheredReports ) ) {
				$this->rep->content = $this->render( $gatheredReports );
			}
		}
	}

	protected function isReadyToSend() :bool {
		return !Services::WpGeneral()->isCron()
			   || !$this->rep->previous instanceof EntryVO
			   || Services::Request()->ts() > $this->rep->interval_end_at;
	}

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$reports = [];
		return array_filter( array_map(
			function ( $reporter ) use ( $reports ) {
				return array_merge( $reports, $reporter->setReport( $this->rep )->build() );
			},
			$this->getCon()
				 ->getModule_Reporting()
				 ->getReportingController()
				 ->getReporters( $this->rep->type )
		) );
	}

	protected function render( array $gatheredReports ) :string {

		switch ( $this->rep->type ) {
			case Constants::REPORT_TYPE_ALERT:
				$renderer = ReportsBuilderAlerts::SLUG;
				break;

			case Constants::REPORT_TYPE_INFO:
			default:
				$renderer = ReportsBuilderInfo::SLUG;
				break;
		}

		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render(
						$renderer,
						[
							'strings' => [
								'time_interval' => $this->getTimeIntervalForDisplay(),
							],
							'vars'    => [
								'reports' => $gatheredReports
							],
						]
					);
	}

	/**
	 * When displaying, we must take into account the GMT offset of the site.
	 */
	protected function getTimeIntervalForDisplay() :string {
		$CStart = Services::Request()->carbon( true )->setTimestamp( $this->rep->interval_start_at );
		$CEnd = Services::Request()->carbon( true )->setTimestamp( $this->rep->interval_end_at );

		switch ( $this->rep->interval ) {
			case 'no_time': // TODO
				$time = __( 'No Time Interval', 'wp-simple-firewall' );
				break;
			case 'hourly':
				$time = sprintf( 'The full hour from %s until %s on %s.',
					$CStart->format( 'H:i' ),
					$CStart->addHours( 1 )->format( 'H:i' ),
					$CEnd->format( 'D, d F (Y)' ) );
				break;
			case 'daily':
				$time = sprintf( 'The entire day of %s.', $CStart->format( 'D j F' ) );
				break;
			case 'weekly':
				$time = sprintf( '1 week from %s until %s (inclusive).',
					$CStart->format( 'D j F' ), $CEnd->format( 'D j F' )
				);
				break;
			case 'monthly':
				$time = sprintf( 'The month of %s.', $CStart->format( 'F, Y' ) );
				break;
			case 'yearly':
				$time = sprintf( 'The year %s', $CStart->format( 'Y' ) );
				break;
			default:
				$time = '';
				break;
		}
		return $time;
	}
}
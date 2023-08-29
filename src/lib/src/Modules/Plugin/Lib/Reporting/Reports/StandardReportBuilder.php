<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ReportsCollatorForAlerts,
	ReportsCollatorForInfo
};
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
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
			$components = $this->buildComponents();
			if ( !empty( $components ) ) {
				$this->rep->content = $this->render( $components );
			}
		}
	}

	protected function isReadyToSend() :bool {
		return !Services::WpGeneral()->isCron()
			   || !$this->rep->previous instanceof Record
			   || Services::Request()->ts() > $this->rep->interval_end_at;
	}

	/**
	 * @return string[]
	 */
	private function buildComponents() :array {
		$reports = [];
		$builders = self::con()
						->getModule_Plugin()
						->getReportingController()
						->getComponentBuilders( $this->rep->type );
		foreach ( $builders as $builder ) {
			$reports[] = self::con()->action_router->render(
				$builder::SLUG,
				[
					'report' => $this->rep->getRawData()
				]
			);
		}

		return \array_filter( \array_map( '\trim', $reports ) );
	}

	protected function render( array $gatheredReports ) :string {

		switch ( $this->rep->type ) {
			case Constants::REPORT_TYPE_ALERT:
				$renderer = ReportsCollatorForAlerts::SLUG;
				break;

			case Constants::REPORT_TYPE_INFO:
			default:
				$renderer = ReportsCollatorForInfo::SLUG;
				break;
		}

		return self::con()->action_router->render(
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
					$CStart->addHours()->format( 'H:i' ),
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
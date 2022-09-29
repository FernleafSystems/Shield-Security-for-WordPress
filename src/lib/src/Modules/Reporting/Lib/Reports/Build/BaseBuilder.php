<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBuilder {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	protected $rep;

	public function __construct( ReportVO $oReport ) {
		$this->rep = $oReport;
	}

	/**
	 * @throws \Exception
	 */
	public function build() {
		if ( $this->isReadyToSend() ) {
			$data = $this->gather();
			if ( !empty( $data ) ) {
				$this->rep->content = $this->render( $data );
			}
		}
	}

	protected function isReadyToSend() :bool {
		return !Services::WpGeneral()->isCron()
			   || empty( $this->rep->previous )
			   || Services::Request()->ts() > $this->rep->interval_end_at;
	}

	/**
	 * @return string[]
	 */
	abstract protected function gather() :array;

	abstract protected function render( array $gathered ) :string;

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
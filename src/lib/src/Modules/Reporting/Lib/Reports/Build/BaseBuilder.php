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

	abstract protected function render( array $aGatheredData ) :string;

	/**
	 * When displaying, we must take into account the GMT offset of the site.
	 * @return string
	 */
	protected function getTimeIntervalForDisplay() {
		$oCStart = Services::Request()->carbon( true )->setTimestamp( $this->rep->interval_start_at );
		$oCEnd = Services::Request()->carbon( true )->setTimestamp( $this->rep->interval_end_at );

		switch ( $this->rep->interval ) {
			case 'no_time': // TODO
				$sTime = __( 'No Time Interval', 'wp-simple-firewall' );
				break;
			case 'hourly':
				$sTime = sprintf( 'The full hour from %s until %s on %s.',
					$oCStart->format( 'H:i' ),
					$oCStart->addHours( 1 )->format( 'H:i' ),
					$oCEnd->format( 'D, d F (Y)' ) );
				break;
			case 'daily':
				$sTime = sprintf( 'The entire day of %s.', $oCStart->format( 'D j F' ) );
				break;
			case 'weekly':
				$sTime = sprintf( '1 week from %s until %s (inclusive).',
					$oCStart->format( 'D j F' ), $oCEnd->format( 'D j F' )
				);
				break;
			case 'monthly':
				$sTime = sprintf( 'The month of %s.', $oCStart->format( 'F, Y' ) );
				break;
			case 'yearly':
				$sTime = sprintf( 'The year %s', $oCStart->format( 'Y' ) );
				break;
			default:
				$sTime = '';
				break;
		}
		return $sTime;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBuild {

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
			$aData = $this->gather();
			if ( !empty( $aData ) ) {
				$this->rep->content = $this->render( $aData );
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function isReadyToSend() {
		return !Services::WpGeneral()->isCron()
			   || empty( $this->rep->previous )
			   || Services::Request()->ts() > $this->rep->interval_end_at;
	}

	/**
	 * @return string[]
	 */
	abstract protected function gather();

	/**
	 * @param array $aGatheredData
	 * @return string
	 */
	abstract protected function render( array $aGatheredData );

	/**
	 * @return string
	 */
	protected function getTimeIntervalForDisplay() {
		$oCStart = ( new Carbon() )->setTimestamp( $this->rep->interval_start_at );
		$oCEnd = ( new Carbon() )->setTimestamp( $this->rep->interval_end_at );
		switch ( $this->rep->interval ) {
			case 'hourly':
				$sTime = sprintf( 'The full hour from %s until %s on %s.',
					$oCStart->format( 'H:i' ),
					$oCStart->addHours( 1 )->format( 'H:i' ),
					$oCEnd->format( 'D j F' ) );
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
				$sTime = sprintf( 'The year %s', $oCStart->year );
				break;
			default:
				$sTime = '';
				break;
		}
		return $sTime;
	}
}
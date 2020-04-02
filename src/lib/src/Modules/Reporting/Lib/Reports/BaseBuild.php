<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

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
}
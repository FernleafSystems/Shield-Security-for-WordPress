<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuildScanAction {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Base\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof ScanActionVO ) {
			throw new \Exception( 'Action VO not provided.' );
		}
		if ( empty( $oAction->id ) ) {
			throw new \Exception( 'Action ID not provided.' );
		}
		$this->setCustomFields();
		$this->setStandardFields();
	}

	/**
	 */
	protected function setStandardFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->ts_start = Services::Request()->ts();
		$oAction->processed_items = 0;
	}

	/**
	 */
	protected function setCustomFields() {
	}
}
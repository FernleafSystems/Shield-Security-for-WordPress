<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseScan {

	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function run() {
		$this->preScan();
		$this->scan();
		$this->postScan();
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof BaseScanActionVO ) {
			throw new \Exception( 'Action VO not provided.' );
		}
		if ( empty( $oAction->scan ) ) {
			throw new \Exception( 'Action Slug not provided.' );
		}
	}

	protected function scan() {
		$action = $this->getScanActionVO();

		if ( empty( $action->items ) ) {
			$action->finished_at = Services::Request()->ts();
		}
		else {
			$this->scanSlice();
			if ( empty( $action->items ) ) {
				$action->finished_at = Services::Request()->ts();
			}
		}

		return $action;
	}

	abstract protected function scanSlice();

	protected function postScan() {
	}
}
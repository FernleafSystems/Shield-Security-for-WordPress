<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

abstract class BuildScanAction {

	use HackGuard\Scan\Controller\ScanControllerConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @return static
	 * @throws \Exception
	 */
	public function build() {
		$this->setScanActionVO( $this->getScanController()->getScanActionVO() );

		$this->setCustomFields();
		$this->buildScanItems();
		$this->setStandardFields();

		return $this;
	}

	abstract protected function buildScanItems();

	protected function setStandardFields() {
		$action = $this->getScanActionVO();
		if ( empty( $action->created_at ) ) {
			$action->created_at = Services::Request()->ts();
			$action->started_at = 0;
			$action->finished_at = 0;
			$action->usleep = (int)( 1000000*max( 0, apply_filters(
					$this->getScanController()->getCon()->prefix( 'scan_block_sleep' ),
					$action::DEFAULT_SLEEP_SECONDS, $action->scan
				) ) );
		}
	}

	protected function setCustomFields() {
	}
}
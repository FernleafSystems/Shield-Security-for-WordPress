<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBuildScanAction {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param bool $bBuildItems
	 * @throws \Exception
	 */
	public function build( $bBuildItems = true ) {
		/** @var BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof BaseScanActionVO ) {
			throw new \Exception( 'Action VO not provided.' );
		}
		if ( empty( $oAction->id ) ) {
			throw new \Exception( 'Action ID not provided.' );
		}

		$this->setCustomFields();
		if ( $bBuildItems && !$oAction->is_items_built ) {
			$this->buildScanItems();
		}
		$this->setStandardFields();
	}

	/**
	 * @throws \Exception
	 */
	public function buildScanItems() {
		/** @var BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( $oAction->is_items_built ) {
			throw new \Exception( 'Attempting to build items while they already exist.' );
		}
		$this->buildItems();
		$oAction->is_items_built = true;
		$oAction->processed_items = 0;
		$oAction->total_scan_items = count( $oAction->items );
	}

	/**
	 */
	abstract protected function buildItems();

	/**
	 */
	protected function setStandardFields() {
		/** @var BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( empty( $oAction->started_at ) ) {
			$oAction->started_at = Services::Request()->ts();
			$oAction->finished_at = 0;
		}
	}

	/**
	 */
	protected function setCustomFields() {
	}
}
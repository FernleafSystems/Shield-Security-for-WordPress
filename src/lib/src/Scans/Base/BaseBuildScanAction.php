<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBuildScanAction {

	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() {
		$action = $this->getScanActionVO();
		if ( !$action instanceof BaseScanActionVO ) {
			throw new \Exception( 'Scan Action VO not provided.' );
		}
		if ( empty( $action->scan ) ) {
			throw new \Exception( 'Scan Slug not provided.' );
		}

		$this->setWhitelists();
		$this->setCustomFields();
		$this->buildScanItems();
		$this->setStandardFields();
	}

	/**
	 * @throws \Exception
	 */
	protected function buildScanItems() {
		$action = $this->getScanActionVO();
		$this->buildItems();
		$action->total_items = count( $action->items );
	}

	abstract protected function buildItems();

	protected function setStandardFields() {
		$action = $this->getScanActionVO();
		if ( empty( $action->created_at ) ) {
			$action->created_at = Services::Request()->ts();
			$action->started_at = 0;
			$action->finished_at = 0;
			$action->usleep = (int)( 1000000*max( 0, apply_filters(
					$this->getCon()->prefix( 'scan_block_sleep' ),
					$action::DEFAULT_SLEEP_SECONDS, $action->scan
				) ) );
		}
	}

	protected function setCustomFields() {
	}

	protected function setWhitelists() {
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		$action = $this->getScanActionVO();
		error_log( var_export($opts->getWhitelistedPathsAsRegex(),true) );
		$action->paths_whitelisted = $opts->getWhitelistedPathsAsRegex();
	}
}
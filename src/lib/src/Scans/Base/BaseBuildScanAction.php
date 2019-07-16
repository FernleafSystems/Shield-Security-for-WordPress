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
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();

		$oAction->file_scan_limit = $oAction->is_async ? $oOpts->getFileScanLimit() : 0;
		$oAction->paths_whitelisted = $oOpts->getMalWhitelistPaths();
		$oAction->patterns_regex = $oOpts->getMalSignaturesRegex();
		$oAction->patterns_simple = $oOpts->getMalSignaturesSimple();
		$oAction->file_exts = [ 'php', 'php5' ];
		$oAction->scan_root_dir = ABSPATH;

		$oAction->files_map = ( new Shield\Scans\Mal\BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
		$oAction->total_scan_items = count( $oAction->files_map );
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * @deprecated 13.0
 */
class Ptg extends BaseForFiles {

	const SCAN_SLUG = 'ptg';
	use PluginCronsConsumer;

	protected function run() {
		parent::run();
	}

	public function onWpLoaded() {
	}

	public function onModuleShutdown() {
	}

	public function runHourlyCron() {
	}

	/**
	 * @param Scans\Ptg\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
	}

	/**
	 * @return Scans\Ptg\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ptg\Utilities\ItemActionHandler();
	}

	public function isReady() :bool {
		return parent::isReady() && $this->getCon()->hasCacheDir();
	}

	/**
	 * @return Scans\Ptg\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Ptg\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}
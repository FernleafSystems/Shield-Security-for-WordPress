<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

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
	 * @return Scans\Ptg\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		/** @var Scans\Ptg\ResultsSet $results */
		$results = parent::getItemsToAutoRepair();

		if ( !$opts->isRepairFilePlugin() || !$opts->isRepairFileTheme() ) {
			if ( $opts->isRepairFileTheme() ) {
				$results = $results->getResultsForThemesContext();
			}
			elseif ( $opts->isRepairFilePlugin() ) {
				$results = $results->getResultsForPluginsContext();
			}

			/** @var Scans\Ptg\ResultItem $item */
			foreach ( $results->getItems() as $item ) {
				if ( $item->is_unrecognised ) {
					$results->removeItem( $item );
				}
			}
		}

		return $results;
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFilePlugin() || $opts->isRepairFileTheme();
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

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'ptg_enable', 'Y' );
	}

	public function isReady() :bool {
		return parent::isReady() && $this->getCon()->hasCacheDir();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new HackGuard\Lib\Snapshots\StoreAction\DeleteAll() )
			->setMod( $this->getMod() )
			->run();
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
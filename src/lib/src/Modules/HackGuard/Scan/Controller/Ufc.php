<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Ufc extends BaseForFiles {

	const SCAN_SLUG = 'ufc';

	/**
	 * @return Scans\Ufc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ufc\Utilities\ItemActionHandler();
	}

	public function canCronAutoDelete() :bool {
		return $this->isCronAutoRepair();
	}

	/**
	 * @param Scans\Ufc\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( !Services::WpFs()->exists( $item->path_full ) ) {
			/** @var HackGuard\DB\ResultItems\Ops\Update $updater */
			$updater = $mod->getDbH_ResultItems()->getQueryUpdater();
			$updater->setItemDeleted( $item->VO->resultitem_id );
		}
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isUfsDeleteFiles();
	}

	public function isEnabled() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->getUnrecognisedFileScannerOption() !== 'disabled';
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Ufc\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Ufc\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}
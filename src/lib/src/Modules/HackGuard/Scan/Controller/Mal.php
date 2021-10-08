<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Mal extends BaseForFiles {

	const SCAN_SLUG = 'mal';

	/**
	 * Can only possibly repair themes, plugins or core files.
	 * @return Scans\Mal\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$oRes = new Scans\Mal\ResultsSet();

		/** @var Scans\Mal\ResultItem $item */
		foreach ( parent::getItemsToAutoRepair()->getAllItems() as $item ) {

			try {
				if ( $opts->isRepairFilePlugin()
					 && ( new WpOrg\Plugin\Files() )->isValidFileFromPlugin( $item->path_full ) ) {
					$oRes->addItem( $item );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			try {
				if ( $opts->isRepairFileTheme()
					 && ( new WpOrg\Theme\Files() )->isValidFileFromTheme( $item->path_full ) ) {
					$oRes->addItem( $item );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			if ( !$opts->isRepairFileWP()
				 && Services::CoreFileHashes()->isCoreFile( $item->path_full ) ) {
				$oRes->addItem( $item );
			}
		}

		return $oRes;
	}

	/**
	 * @param Scans\Ufc\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( !Services::WpFs()->exists( $item->path_full ) ) {
			/** @var Update $updater */
			$updater = $mod->getDbH_ResultItems()->getQueryUpdater();
			$updater->setItemDeleted( $item->VO->resultitem_id );
		}
	}

	/**
	 * @return Scans\Mal\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Mal\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileAuto();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'mal_scan_enable', 'Y' );
	}

	/**
	 * @return Scans\Mal\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Mal\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}
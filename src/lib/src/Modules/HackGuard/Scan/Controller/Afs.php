<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Afs extends BaseForFiles {

	const SCAN_SLUG = 'afs';

	/**
	 * Can only possibly repair themes, plugins or core files.
	 * @return Scans\Afs\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$repairResults = new Scans\Afs\ResultsSet();

		/** @var Scans\Afs\ResultItem $item */
		foreach ( parent::getItemsToAutoRepair()->getAllItems() as $item ) {

			try {
				if ( $opts->isRepairFilePlugin()
					 && ( new WpOrg\Plugin\Files() )->isValidFileFromPlugin( $item->path_full ) ) {
					$repairResults->addItem( $item );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			try {
				if ( $opts->isRepairFileTheme()
					 && ( new WpOrg\Theme\Files() )->isValidFileFromTheme( $item->path_full ) ) {
					$repairResults->addItem( $item );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			if ( !$opts->isRepairFileWP()
				 && Services::CoreFileHashes()->isCoreFile( $item->path_full ) ) {
				$repairResults->addItem( $item );
			}
		}

		return $repairResults;
	}

	/**
	 * @param Scans\Afs\ResultItem $item
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
	 * @return Scans\Afs\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Afs\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileAuto();
	}

	public function isEnabled() :bool {
		return true;
	}

	/**
	 * @return Scans\Afs\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Afs\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function getResultsForDisplay() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$actualResults = $this->getNewResultsSet();
		/** @var Scans\Afs\ResultItem $item */
		foreach ( parent::getResultsForDisplay()->getItems() as $item ) {
			if ( $opts->getMalConfidenceBoundary() > $item->mal_fp_confidence ) {
				$actualResults->addItem( $item );
			}
		}
		return $actualResults;
	}
}
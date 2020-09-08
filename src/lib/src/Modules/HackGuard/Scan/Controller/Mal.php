<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Mal extends Base {

	/**
	 * Can only possibly repair themes, plugins or core files.
	 * @return Scans\Mal\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$oRes = new Scans\Mal\ResultsSet();

		/** @var Scans\Mal\ResultItem $oItem */
		foreach ( parent::getItemsToAutoRepair()->getAllItems() as $oItem ) {

			try {
				if ( $oOpts->isRepairFilePlugin()
					 && ( new WpOrg\Plugin\Files() )->isValidFileFromPlugin( $oItem->path_full ) ) {
					$oRes->addItem( $oItem );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			try {
				if ( $oOpts->isRepairFileTheme()
					 && ( new WpOrg\Theme\Files() )->isValidFileFromTheme( $oItem->path_full ) ) {
					$oRes->addItem( $oItem );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}

			if ( !$oOpts->isRepairFileWP()
				 && Services::CoreFileHashes()->isCoreFile( $oItem->path_full ) ) {
				$oRes->addItem( $oItem );
			}
		}

		return $oRes;
	}

	/**
	 * @param Scans\Mal\ResultItem $oItem
	 * @return bool
	 */
	protected function isResultItemStale( $oItem ) {
		return !Services::WpFs()->exists( $oItem->path_full );
	}

	/**
	 * @return Scans\Mal\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Mal\Utilities\ItemActionHandler();
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isRepairFileAuto();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isOpt( 'mal_scan_enable', 'Y' );
	}
}
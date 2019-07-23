<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Scan extends Shield\Scans\Base\BaseScan {

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oPluginHashes;

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oThemeHashes;

	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( (int)$oAction->item_processing_limit > 0 ) {
			$aSlice = array_slice( $oAction->scan_items, 0, $oAction->item_processing_limit );
			$oAction->scan_items = array_slice( $oAction->scan_items, $oAction->item_processing_limit );
		}
		else {
			$aSlice = $oAction->scan_items;
			$oAction->scan_items = [];
		}

		$oAction->processed_items += count( $aSlice );

		$oTempRs = $oAction->getNewResultsSet();

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oItemScanner = $this->getItemScanner();
		foreach ( $aSlice as $sSlug => $sContext ) {
			$oNewRes = null;

			$bUseStaticHashes = true;

			// use live hashes if it's a WP.org plugin
			if ( $oWpPlugins->isWpOrg( $sSlug ) ) {
				try {
					$oNewRes = ( new PluginWporgScanner() )
						->setScanActionVO( $oAction )
						->scan( $sSlug );
					$bUseStaticHashes = false;
				}
				catch ( \Exception $oE ) {
					$bUseStaticHashes = true;
				}
			}

			if ( $bUseStaticHashes ) {
				if ( $sContext == 'plugins' ) {
					$oNewRes = $oItemScanner->scan(
						$oWpPlugins->getInstallationDir( $sSlug ),
						$this->getPluginHashes()->getSnapItem( $sSlug )[ 'hashes' ]
					);
				}
				else {
					$oNewRes = $oItemScanner->scan(
						$oWpThemes->getInstallationDir( $sSlug ),
						$this->getThemeHashes()->getSnapItem( $sSlug )[ 'hashes' ]
					);
				}
			}

			if ( $oNewRes instanceof ResultsSet ) {
				$oNewRes->setSlugOnAllItems( $sSlug )
						->setContextOnAllItems( $sContext );
				( new Shield\Scans\Helpers\CopyResultsSets() )->copyTo( $oNewRes, $oTempRs );
			}
		}

		if ( $oTempRs->hasItems() ) {
			$aNewItems = [];
			foreach ( $oTempRs->getAllItems() as $oNewRes ) {
				$aNewItems[] = $oNewRes->getRawDataAsArray();
			}
			if ( empty( $oAction->results ) ) {
				$oAction->results = [];
			}
			$oAction->results = array_merge( $oAction->results, $aNewItems );
		}
	}

	/**
	 * @return Snapshots\Store
	 */
	private function getPluginHashes() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( empty( $this->oPluginHashes ) ) {
			$this->oPluginHashes = ( new Shield\Scans\Ptg\Snapshots\Store() )
				->setContext( 'plugins' )
				->setStorePath( $oAction->hashes_base_path );
		}
		return $this->oPluginHashes;
	}

	/**
	 * @return Snapshots\Store
	 */
	private function getThemeHashes() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( empty( $this->oThemeHashes ) ) {
			$this->oThemeHashes = ( new Shield\Scans\Ptg\Snapshots\Store() )
				->setContext( 'themes' )
				->setStorePath( $oAction->hashes_base_path );
		}
		return $this->oThemeHashes;
	}

	/**
	 * @return ItemScanner
	 */
	protected function getItemScanner() {
		return ( new ItemScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}
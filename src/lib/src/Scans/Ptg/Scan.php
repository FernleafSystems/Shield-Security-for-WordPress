<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

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

		$oTempRs = $oAction->getNewResultsSet();

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oItemScanner = $this->getItemScanner();
		$oCopier = new Shield\Scans\Helpers\CopyResultsSets();

		// check we can even ping the WP Hashes API.
		$bLiveHashesPing = ( new WpHashes\ApiPing() )->ping();
		foreach ( $oAction->items as $sSlug => $sContext ) {
			$oNewRes = null;

			$bUseStaticHashes = true;

			// use live hashes if it's a WP.org plugin/theme
			if ( $bLiveHashesPing ) {
				if ( $sContext == 'plugins' ) {

					if ( $bLiveHashesPing && $oWpPlugins->isWpOrg( $sSlug ) ) {
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
						$aHashes = $this->getPluginHashes()->getSnapItem( $sSlug )[ 'hashes' ];
						if ( !empty( $aHashes ) ) {
							$oNewRes = $oItemScanner->scan(
								$oWpPlugins->getInstallationDir( $sSlug ),
								$this->getPluginHashes()->getSnapItem( $sSlug )[ 'hashes' ]
							);
						}
					}
				}
				else { // THEMES:

					if ( $bLiveHashesPing && $oWpThemes->isWpOrg( $sSlug ) ) {
						// TODO
					}

					if ( $bUseStaticHashes ) {
						$aHashes = $this->getThemeHashes()->getSnapItem( $sSlug )[ 'hashes' ];
						if ( !empty( $aHashes ) ) {
							$oNewRes = $oItemScanner->scan(
								$oWpThemes->getInstallationDir( $sSlug ),
								$this->getThemeHashes()->getSnapItem( $sSlug )[ 'hashes' ]
							);
						}
					}
				}
			}

			if ( $oNewRes instanceof ResultsSet ) {
				$oNewRes->setSlugOnAllItems( $sSlug )
						->setContextOnAllItems( $sContext );
				$oCopier->copyTo( $oNewRes, $oTempRs );
			}
		}

		$aNewItems = [];
		if ( $oTempRs->hasItems() ) {
			foreach ( $oTempRs->getAllItems() as $oItem ) {
				$aNewItems[] = $oItem->getRawDataAsArray();
			}
		}
		$oAction->results = $aNewItems;
	}

	/**
	 * @return Snapshots\Store
	 */
	private
	function getPluginHashes() {
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
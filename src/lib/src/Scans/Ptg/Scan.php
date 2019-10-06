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

	/**
	 */
	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = $oAction->getNewResultsSet();

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oCopier = new Shield\Scans\Helpers\CopyResultsSets();

		// check we can even ping the WP Hashes API.
		$bUseLiveHashes = ( new WpHashes\ApiPing() )->ping();
		foreach ( $oAction->items as $sFileOrStylesheet => $sContext ) {
			$oNewRes = null;

			$bUseStaticHashes = true;

			if ( $sContext == 'plugins' ) {
				$oAsset = $oWpPlugins->getPluginAsVo( $sFileOrStylesheet );
			}
			else {
				$oAsset = $oWpThemes->getThemeAsVo( $sFileOrStylesheet );
			}
			if ( empty( $oAsset ) ) {
				error_log( sprintf( '"%s" Asset "%s" cannot be loaded', $sContext, $sFileOrStylesheet ) );
				continue;
			}

			// use live hashes if it's a WP.org plugin/theme
			if ( $bUseLiveHashes ) {

				try {
					$oNewRes = ( new WporgAssetScanner() )
						->setScanActionVO( $oAction )
						->setAsset( $oAsset )
						->scan();
					$bUseStaticHashes = false;
				}
				catch ( \Exception $oE ) {
					$bUseStaticHashes = true;
				}
			}

			if ( $bUseStaticHashes ) { // Live hashes didn't work.
				if ( $sContext == 'plugins' ) {
					$sAssetDir = $oWpPlugins->getInstallationDir( $oAsset->file );
					$aSnapHashes = $this->getPluginHashes()->getSnapItem( $sFileOrStylesheet )[ 'hashes' ];
				}
				else { // THEMES:
					$sAssetDir = $oAsset->wp_theme->get_stylesheet_directory();
					$aSnapHashes = $this->getThemeHashes()->getSnapItem( $sFileOrStylesheet )[ 'hashes' ];
				}

				try {
					$oNewRes = $this->getItemScanner()
									->scan( $sAssetDir, $aSnapHashes );
				}
				catch ( \Exception $oE ) {
				}
			}

			if ( $oNewRes instanceof ResultsSet ) {
				$oNewRes->setSlugOnAllItems( $sFileOrStylesheet )
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
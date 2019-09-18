<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oTempRs = $oAction->getNewResultsSet();

		$oCopier = new Shield\Scans\Helpers\CopyResultsSets();
		foreach ( $oAction->items as $sFile => $sContext ) {
			$oNewRes = $this->scanItem( $sContext, $sFile );
			if ( $oNewRes instanceof Shield\Scans\Base\BaseResultsSet ) {
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
	 * @param string $sContext
	 * @param string $sFile
	 * @return ResultsSet
	 */
	protected function scanItem( $sContext, $sFile ) {
		$oResultsSet = new ResultsSet();

		if ( $sContext == 'plugins' ) {
			$oWpPlugins = Services::WpPlugins();
			$sSlug = $oWpPlugins->getSlug( $sFile );
			if ( empty( $sSlug ) ) {
				$sSlug = dirname( $sFile );
			}
			$sVersion = $oWpPlugins->getPluginAsVo( $sFile )->Version;
			$oLookup = new Vulnerabilities\Plugin();
		}
		else {
			$sSlug = $sFile;
			$sVersion = Services::WpThemes()->getTheme( $sSlug )->get( 'Version' );
			$oLookup = new Vulnerabilities\Theme();
		}

		$aVulns = $oLookup->getVulnerabilities( $sSlug, $sVersion );
		$aVulns = array_filter( array_map(
			function ( $aVuln ) {
				return empty( $aVuln ) ? null
					: ( new Shield\Scans\Wpv\WpVulnDb\WpVulnVO() )->applyFromArray( $aVuln );
			},
			( is_array( $aVulns ) ? $aVulns : [] )
		) );

		/** @var Shield\Scans\Wpv\WpVulnDb\WpVulnVO[] $aVulns */
		foreach ( $aVulns as $oVo ) {
			$oItem = new ResultItem();
			$oItem->slug = $sFile;
			$oItem->context = $sContext;
			$oItem->wpvuln_id = $oVo->id;
			$oItem->wpvuln_vo = $oVo->getRawDataAsArray();
			$oResultsSet->addItem( $oItem );
		}

		return $oResultsSet;
	}
}
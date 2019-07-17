<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PluginScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class PluginScanner extends ItemScanner {

	use Shield\Scans\Base\ScanActionConsumer;

	/**
	 * @param string $sFile
	 * @return ResultsSet|null
	 */
	public function scan( $sFile ) {
		$oResultsSet = null;

		$oWpPlugins = Services::WpPlugins();

		$sSlug = $oWpPlugins->getSlug( $sFile );
		if ( empty( $sSlug ) ) {
			$sSlug = dirname( $sFile );
		}

		$aVos = $this->retrieveForSlug( $sSlug, 'plugins' );
		if ( !empty( $aVos ) ) {
			$aVulns = $this->filterAgainstVersion( $aVos, $oWpPlugins->getPluginAsVo( $sFile )->Version );

			if ( !empty( $aVulns ) ) {
				$oResultsSet = new ResultsSet();
				foreach ( $aVulns as $oVo ) {
					$oItem = new ResultItem();
					$oItem->slug = $sFile;
					$oItem->context = 'plugins';
					$oItem->wpvuln_id = $oVo->id;
					$oItem->wpvuln_vo = $oVo->getRawDataAsArray();
					$oResultsSet->addItem( $oItem );
				}
			}
		}

		return $oResultsSet;
	}
}
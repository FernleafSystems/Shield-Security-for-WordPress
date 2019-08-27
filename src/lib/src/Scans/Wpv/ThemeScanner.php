<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ThemeScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class ThemeScanner extends ItemScanner {

	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param string $sSlug
	 * @return ResultsSet|null
	 */
	public function scan( $sSlug ) {
		$oResultsSet = null;

		$aVos = $this->retrieveForSlug( $sSlug, 'themes' );
		if ( !empty( $aVos ) ) {
			$oTheme = Services::WpThemes()->getTheme( $sSlug );
			$aVulns = $this->filterAgainstVersion( $aVos, $oTheme->get( 'Version' ) );

			if ( !empty( $aVulns ) ) {
				$oResultsSet = new ResultsSet();
				foreach ( $aVulns as $oVo ) {
					$oItem = new ResultItem();
					$oItem->slug = $sSlug;
					$oItem->context = 'themes';
					$oItem->wpvuln_id = $oVo->id;
					$oItem->wpvuln_vo = $oVo->getRawDataAsArray();
					$oResultsSet->addItem( $oItem );
				}
			}
		}

		return $oResultsSet;
	}
}
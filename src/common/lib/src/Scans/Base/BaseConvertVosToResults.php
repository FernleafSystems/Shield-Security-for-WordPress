<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertVosToResults {

	/**
	 * @param \ICWP_WPSF_ScannerEntryVO[] $oVos
	 * @return BaseResultsSet
	 */
	public function convert( $oVos ) {

		foreach ( $oVos as $oVo ) {
			$aVos[] = $this->convertItem( $oVo );
		}
		return $aVos;
	}

	/**
	 * @param \ICWP_WPSF_ScannerEntryVO $oVo
	 * @return BaseResultItem
	 */
	abstract public function convertItem( $oVo );
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertResultsToVos {

	/**
	 * @param BaseResultsSet $oResults
	 * @return \ICWP_WPSF_ScannerEntryVO[]
	 */
	public function convert( $oResults ) {
		$aVos = array();
		foreach ( $oResults->getAllItems() as $oIt ) {
			/** @var BaseResultItem $oIt */
			$aVos[ $oIt->generateHash() ] = $this->convertItem( $oIt );
		}
		return $aVos;
	}

	/**
	 * @param BaseResultItem $oIt
	 * @return \ICWP_WPSF_ScannerEntryVO
	 */
	abstract public function convertItem( $oIt );
}
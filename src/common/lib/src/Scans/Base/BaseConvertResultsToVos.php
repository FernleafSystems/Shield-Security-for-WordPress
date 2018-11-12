<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

/**
 * Class BaseConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertResultsToVos {

	/**
	 * @param BaseResultsSet $oResults
	 * @return Scanner\EntryVO[]
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
	 * @return Scanner\EntryVO
	 */
	abstract public function convertItem( $oIt );
}
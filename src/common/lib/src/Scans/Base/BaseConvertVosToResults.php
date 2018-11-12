<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

/**
 * Class BaseConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertVosToResults {

	/**
	 * @param Scanner\EntryVO[] $oVos
	 * @return BaseResultsSet
	 */
	public function convert( $oVos ) {

		foreach ( $oVos as $oVo ) {
			$aVos[] = $this->convertItem( $oVo );
		}
		return $aVos;
	}

	/**
	 * @param Scanner\EntryVO $oVo
	 * @return BaseResultItem
	 */
	abstract public function convertItem( $oVo );
}
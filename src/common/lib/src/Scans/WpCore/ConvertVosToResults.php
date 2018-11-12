<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class ConvertVosToResults extends Scans\Base\BaseConvertVosToResults {

	/**
	 * @param Scanner\EntryVO[] $oVos
	 * @return ResultsSet
	 */
	public function convert( $oVos ) {
		$oRes = new ResultsSet();
		foreach ( $oVos as $oVo ) {
			$oRes->addItem( $this->convertItem( $oVo ) );
		}
		return $oRes;
	}

	/**
	 * @param Scanner\EntryVO $oVo
	 * @return ResultItem
	 */
	public function convertItem( $oVo ) {
	}
}
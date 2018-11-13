<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
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
		return ( new ResultItem() )->applyFromArray( $oVo->data );
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class ConvertVosToResults extends Shield\Scans\Base\BaseConvertVosToResults {

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $oVos
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
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return ResultItem
	 */
	public function convertItem( $oVo ) {
		return ( new ResultItem() )->applyFromArray( $oVo->meta );
	}
}
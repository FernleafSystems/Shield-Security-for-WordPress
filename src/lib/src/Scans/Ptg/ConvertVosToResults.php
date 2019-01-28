<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ConvertVosToResults extends Scans\Base\BaseConvertVosToResults {

	/**
	 * @param EntryVO[] $oVos
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
	 * @param EntryVO $oVo
	 * @return ResultItem
	 */
	public function convertItem( $oVo ) {
		return ( new ResultItem() )->applyFromArray( $oVo->meta );
	}
}
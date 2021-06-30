<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class ConvertVosToResults extends Shield\Scans\Base\BaseConvertVosToResults {

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $VOs
	 * @return ResultsSet
	 */
	public function convert( $VOs ) {
		$oRes = new ResultsSet();
		foreach ( $VOs as $oVo ) {
			$oRes->addItem( $this->convertItem( $oVo ) );
		}
		return $oRes;
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $VO
	 * @return ResultItem
	 */
	public function convertItem( $VO ) {
		return ( new ResultItem() )->applyFromArray( $VO->meta );
	}
}
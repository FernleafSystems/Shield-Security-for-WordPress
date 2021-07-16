<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ConvertVosToResults extends Scans\Base\BaseConvertVosToResults {

	/**
	 * @param EntryVO[] $VOs
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
	 * @param EntryVO $VO
	 * @return ResultItem
	 */
	public function convertItem( $VO ) {
		return ( new ResultItem() )->applyFromArray( $VO->meta );
	}
}
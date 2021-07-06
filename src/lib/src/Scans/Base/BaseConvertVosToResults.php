<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;

/**
 * Class BaseConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertVosToResults {

	/**
	 * @param EntryVO[] $VOs
	 * @return BaseResultsSet
	 */
	public function convert( $VOs ) {
		$oRes = new BaseResultsSet();
		foreach ( $VOs as $oVo ) {
			$oRes->addItem( $this->convertItem( $oVo ) );
		}
		return $oRes;
	}

	/**
	 * @param EntryVO $VO
	 * @return BaseResultItem
	 */
	abstract public function convertItem( $VO );
}
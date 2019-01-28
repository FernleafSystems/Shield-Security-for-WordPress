<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;

/**
 * Class BaseConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseConvertVosToResults {

	/**
	 * @param EntryVO[] $oVos
	 * @return BaseResultsSet
	 */
	public function convert( $oVos ) {
		$oRes = new BaseResultsSet();
		foreach ( $oVos as $oVo ) {
			$oRes->addItem( $this->convertItem( $oVo ) );
		}
		return $oRes;
	}

	/**
	 * @param EntryVO $oVo
	 * @return BaseResultItem
	 */
	abstract public function convertItem( $oVo );
}
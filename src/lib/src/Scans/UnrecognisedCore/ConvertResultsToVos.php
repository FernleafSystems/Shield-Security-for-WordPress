<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore
 */
class ConvertResultsToVos extends Scans\Base\BaseConvertResultsToVos {

	/**
	 * @param ResultItem $oIt
	 * @return EntryVO
	 */
	public function convertItem( $oIt ) {
		$oVo = new EntryVO();
		$oVo->hash = $oIt->hash;
		$oVo->data = $oIt->getData();
		$oVo->scan = $oIt::SCAN_RESULT_TYPE;
		return $oVo;
	}
}
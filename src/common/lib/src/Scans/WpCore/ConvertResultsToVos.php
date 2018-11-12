<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class ConvertResultsToVos extends Scans\Base\BaseConvertResultsToVos {

	/**
	 * @param ResultItem $oIt
	 * @return Scanner\EntryVO
	 */
	public function convertItem( $oIt ) {
		$oVo = new Scanner\EntryVO();
		$oVo->hash = $oIt->hash;
		$oVo->data = $oIt->getData();
		$oVo->description = 'WordPress core file discovered to be modified from original';
		$oVo->scan = $oIt::SCAN_RESULT_TYPE;
		return $oVo;
	}
}
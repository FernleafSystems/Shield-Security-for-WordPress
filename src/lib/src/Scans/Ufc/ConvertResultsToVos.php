<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class ConvertResultsToVos extends Scans\Base\BaseConvertResultsToVos {

	/**
	 * @param ResultItem $oIt
	 * @return EntryVO
	 */
	public function convertItem( $oIt ) {
		$oVo = parent::convertItem( $oIt );
		$oVo->scan = $oIt::SCAN_RESULT_TYPE;
		return $oVo;
	}
}
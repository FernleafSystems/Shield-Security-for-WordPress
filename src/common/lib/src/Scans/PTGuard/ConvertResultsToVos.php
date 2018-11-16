<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertResultsToVos
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard
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
		$oVo->description = sprintf(
			'%s file discovered to be %s',
			ucfirst( $oIt->context ),
			$oIt->is_missing ? 'missing' : ( $oIt->is_different ? 'modified' : 'unrecognised' )
		);
		$oVo->scan = $oIt::SCAN_RESULT_TYPE;
		return $oVo;
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore
 */
class ResultItem extends Base\BaseResultItem {

	const SCAN_RESULT_TYPE = 'ufc';

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( $this->path_full );
	}
}
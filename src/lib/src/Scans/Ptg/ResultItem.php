<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @property string slug
 * @property string context
 * @property string is_unrecognised
 * @property string is_different
 * @property string is_missing
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ResultItem extends Base\BaseResultItem {

	const SCAN_RESULT_TYPE = 'ptg';

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( $this->path_full );
	}
}
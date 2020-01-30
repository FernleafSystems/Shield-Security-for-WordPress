<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class ResultItem extends Base\BaseResultItem {

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( $this->path_full );
	}
}
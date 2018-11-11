<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @property string md5_file_wp
 * @property bool   is_checksumfail
 * @property bool   is_missing
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class ResultItem extends Base\BaseResultItem {

	/**
	 * @return bool
	 */
	public function isReady() {
		return !empty( $this->path_full ) && !empty( $this->md5_file_wp ) && !empty( $this->path_fragment );
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @property string md5_file_wp
 * @property bool   is_checksumfail
 * @property bool   is_missing
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ResultItem extends Base\BaseResultItem {

	const SCAN_RESULT_TYPE = 'wcf';

	/**
	 * @return string
	 */
	public function generateHash() {
		return md5( $this->path_full );
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return !empty( $this->path_full ) && !empty( $this->md5_file_wp ) && !empty( $this->path_fragment );
	}
}
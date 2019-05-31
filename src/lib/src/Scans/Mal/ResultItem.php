<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string $path_full
 * @property string $path_fragment - relative to ABSPATH
 * @property bool   $is_mal
 * @property string $mal_sig
 * @property int[]  $file_lines
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ResultItem extends Base\BaseResultItem {

	const SCAN_RESULT_TYPE = 'mal';

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
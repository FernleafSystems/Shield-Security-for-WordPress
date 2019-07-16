<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string  $id
 * @property int     $ts_start
 * @property int     $ts_finish
 * @property bool    $is_async
 * @property int     $file_scan_limit
 * @property int     $processed_items
 * @property int     $total_scan_items
 * @property string  $tmp_dir
 * @property array[] $results
 */
class ScanActionVO {

	use StdClassAdapter;

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getNewResultItem() {
		return new BaseResultItem();
	}

	/**
	 * @return BaseResultsSet|mixed
	 */
	public function getNewResultsSet() {
		return new BaseResultsSet();
	}
}
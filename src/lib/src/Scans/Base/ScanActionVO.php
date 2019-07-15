<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string  id
 * @property array   working_data
 * @property int     ts_start
 * @property int     ts_finish
 * @property bool    is_async
 * @property int     file_scan_limit
 * @property int     $total_scan_items
 * @property array[] results
 */
class ScanActionVO {

	use StdClassAdapter;
}
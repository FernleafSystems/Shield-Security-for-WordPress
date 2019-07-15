<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class AsyncActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions
 * @property string  id
 * @property array   working_data
 * @property int     ts_start
 * @property int     ts_finish
 * @property bool    is_async
 * @property int     file_scan_limit
 * @property array[] results
 */
class ScanActionVO {

	use StdClassAdapter;
}
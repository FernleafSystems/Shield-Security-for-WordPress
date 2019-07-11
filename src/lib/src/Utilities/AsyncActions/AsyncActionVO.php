<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class AsyncActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions
 * @property string id
 * @property array  working_data
 * @property int    ts_start
 */
class AsyncActionVO {

	use StdClassAdapter;
}
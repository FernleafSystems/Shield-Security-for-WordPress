<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports\EntryVO;

/**
 * @property int           $rid
 * @property string        $type
 * @property string        $interval
 * @property int           $interval_start_at
 * @property int           $interval_end_at
 * @property string        $content
 * @property EntryVO|false $previous
 */
class ReportVO {

	use DynProperties;
}
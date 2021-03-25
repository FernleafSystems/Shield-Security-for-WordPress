<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports\EntryVO;

/**
 * Class ReportVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build
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
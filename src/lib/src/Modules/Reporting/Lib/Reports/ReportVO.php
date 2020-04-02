<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;

/**
 * Class ReportVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports
 * @property int                             $rid
 * @property string                          $type
 * @property string                          $interval
 * @property int                             $interval_start_at
 * @property int                             $interval_end_at
 * @property int                             $content
 * @property Databases\Reports\EntryVO|false $previous
 */
class ReportVO {

	use StdClassAdapter;
}
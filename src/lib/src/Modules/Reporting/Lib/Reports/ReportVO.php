<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\DB\Report\Ops\Record;

/**
 * @property string       $type
 * @property string       $interval
 * @property int          $interval_start_at
 * @property int          $interval_end_at
 * @property string       $content
 * @property Record|false $previous
 */
class ReportVO extends DynPropertiesClass {

}
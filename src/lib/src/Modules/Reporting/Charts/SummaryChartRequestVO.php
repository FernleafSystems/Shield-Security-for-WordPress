<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;

/**
 * Class ChartRequestVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts
 * @property string   $render_location
 * @property string   $interval
 * @property string   $ticks
 * @property string[] $events
 * @property array    $chart_params
 */
class SummaryChartRequestVO {

	const LOCATION_STATCARD = 'insights-overview-statcard';
	use DynProperties;
}
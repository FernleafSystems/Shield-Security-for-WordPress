<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Charts;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;

/**
 * Class ChartRequestVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Charts
 * @property string   $render_location
 * @property string   $interval
 * @property string   $ticks
 * @property string[] $events
 * @property array    $chart_params
 */
class ChartRequestVO {

	const LOCATION_STATCARD = 'insights-overview-statcard';
	use DynProperties;
}
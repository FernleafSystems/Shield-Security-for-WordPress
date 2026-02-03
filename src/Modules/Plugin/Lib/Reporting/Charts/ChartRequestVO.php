<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string   $interval
 * @property string   $ticks
 * @property string[] $events
 * @property bool     $combine_events
 * @property array    $chart_params
 */
class ChartRequestVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'interval':
				if ( empty( $value ) ) {
					$value = 'weekly';
				}
				break;
			default:
				break;
		}
		return $value;
	}
}
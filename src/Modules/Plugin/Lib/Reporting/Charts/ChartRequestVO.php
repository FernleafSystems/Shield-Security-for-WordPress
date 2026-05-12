<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string   $period_key
 * @property string[] $event_keys
 */
class ChartRequestVO extends DynPropertiesClass {

	public function applyFromArray( array $data, array $restrictedKeys = [] ) :self {
		return parent::applyFromArray( [
			'period_key' => ChartOptions::normalizePeriodKey( (string)( $data[ 'period_key' ] ?? '' ) ),
			'event_keys' => ChartOptions::normalizeEventKeys( \is_array( $data[ 'event_keys' ] ?? null ) ? $data[ 'event_keys' ] : [] ),
		], $restrictedKeys );
	}
}

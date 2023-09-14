<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int $last_upgrade_at
 */
class TrackingVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$val = parent::__get( $key );
		switch ( $key ) {
			case 'last_upgrade_at':
				$val = (int)$val;
				break;
			default:
				break;
		}
		return $val;
	}
}
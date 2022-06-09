<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int   $decision_update_started_at
 * @property int   $last_decision_update_at
 * @property int   $last_decision_update_attempt_at
 * @property array $cs_auths
 */
class CrowdSecCfg extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'cs_auths':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}
}
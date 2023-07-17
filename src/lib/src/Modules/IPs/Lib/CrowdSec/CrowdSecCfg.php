<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int   $decisions_updated_at
 * @property int   $decisions_update_attempt_at
 * @property array $cs_auths
 */
class CrowdSecCfg extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'cs_auths':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}
}
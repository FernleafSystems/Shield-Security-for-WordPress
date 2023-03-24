<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $ip
 * @property string $ua
 * @property int    $expiration
 * @property int    $login
 * @property array  $shield
 * /** Not Stored:
 * @property bool   $valid
 * @property string $token
 * @property string $hashed_token
 */
class SessionVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'valid':
				$value = (bool)$value;
				break;

			default:
				break;
		}
		return $value;
	}
}
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

}
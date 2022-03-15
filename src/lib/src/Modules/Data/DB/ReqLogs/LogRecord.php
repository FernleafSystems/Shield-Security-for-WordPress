<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;

/**
 * @property string $ip
 * @property string $rid
 * @property string $type
 * @property string $path
 * @property int    $code
 * @property string $verb
 */
class LogRecord extends Record {

}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * @property string $hash
 * @property array  $meta
 * @property string $scan
 * @property int    $severity
 * @property int    $ignored_at
 * @property int    $notified_at
 * @property int    $attempt_repair_at
 */
class EntryVO extends Base\EntryVO {

}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops;

/**
 * @property int    $scan_ref
 * @property string $hash
 * @property string $item_id
 * @property string $item_type
 * @property int    $ignored_at
 * @property int    $notified_at
 * @property int    $attempt_repair_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

}
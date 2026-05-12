<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

/**
 * @property string $scan
 * @property string $item_type
 * @property string $item_id
 * @property string $asset_type
 * @property string $asset_key
 * @property int    $ignored_at
 * @property int    $notified_at
 * @property int    $auto_filtered_at
 * @property int    $attempt_repair_at
 * @property int    $last_seen_at
 * @property int    $resolved_at
 * @property string $resolution_reason
 * @property int    $item_repaired_at legacy compatibility column
 * @property int    $item_deleted_at legacy compatibility column
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

}

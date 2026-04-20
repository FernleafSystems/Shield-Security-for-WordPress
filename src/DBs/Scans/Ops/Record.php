<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops;

/**
 * @property string $scan
 * @property string $status
 * @property string $scope_type
 * @property string $scope_key
 * @property string $trigger
 * @property int    $started_at
 * @property int    $last_process_at
 * @property int    $ready_at
 * @property int    $finished_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

}

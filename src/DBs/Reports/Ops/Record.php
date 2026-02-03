<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops;

/**
 * @property string $type
 * @property string $interval_length
 * @property string $unique_id
 * @property string $title
 * @property string $content
 * @property bool   $protected
 * @property int    $interval_start_at
 * @property int    $interval_end_at
 * @property int    $created_at - sent at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string $ip
 * @property int    $transgressions
 * @property bool   $is_range
 * @property string $label
 * @property string $list
 * @property int    $last_access_at
 * @property int    $blocked_at
 */
class EntryVO extends Base\EntryVO {

}
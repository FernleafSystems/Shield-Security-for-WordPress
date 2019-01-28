<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string hash
 * @property array  meta
 * @property string scan
 * @property int    severity
 * @property int    discovered_at
 * @property int    ignored_at
 * @property int    notified_at
 */
class EntryVO extends Base\EntryVO {
}